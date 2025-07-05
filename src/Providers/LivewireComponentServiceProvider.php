<?php

namespace Mhmiton\LaravelModulesLivewire\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use Mhmiton\LaravelModulesLivewire\Support\ModuleVoltComponentRegistry;
use Mhmiton\LaravelModulesLivewire\View\ModuleVoltViewFactory;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

class LivewireComponentServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerModuleComponents();

        $this->registerCustomModuleComponents();

        $this->registerModuleVoltViewFactory();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    protected function registerModuleComponents()
    {
        $modules = \Nwidart\Modules\Facades\Module::toCollection();

        $modulesLivewireNamespace = config('modules-livewire.namespace', 'Livewire');

        $modules->each(function ($module) use ($modulesLivewireNamespace) {
            $directory = (string) Str::of($module->getAppPath())
                ->append('/'.$modulesLivewireNamespace)
                ->replace(['\\'], '/');

            $moduleNamespace = method_exists($module, 'getNamespace')
                ? $module->getNamespace()
                : config('modules.namespace', 'Modules');

            $namespace = $moduleNamespace.'\\'.$module->getName().'\\'.$modulesLivewireNamespace;

            $this->registerComponentDirectory($directory, $namespace, $module->getLowerName().'::');

            if (class_exists(\Livewire\Volt\Volt::class)) {
                $this->registerVoltComponents(
                    $module->getPath(),
                    $module->getLowerName(),
                    $namespace,
                    config('modules-livewire.volt_view_namespaces')
                );
            }
        });
    }

    protected function registerCustomModuleComponents()
    {
        $modules = collect(config('modules-livewire.custom_modules', []));

        $modules->each(function ($module, $moduleName) {
            $moduleLivewireNamespace = $module['namespace'] ?? config('modules-livewire.namespace', 'Livewire');

            $directory = (string) Str::of($module['path'] ?? '')
                ->append('/'.$moduleLivewireNamespace)
                ->replace(['\\'], '/');

            $namespace = ($module['module_namespace'] ?? $moduleName).'\\'.$moduleLivewireNamespace;

            $lowerName = $module['name_lower'] ?? strtolower($moduleName);

            $this->registerComponentDirectory($directory, $namespace, $lowerName.'::');

            if (class_exists(\Livewire\Volt\Volt::class)) {
                $this->registerVoltComponents($module['path'], $lowerName, $namespace, $module['volt_view_namespaces']);
            }
        });
    }

    protected function registerVoltComponents($path, $aliasPrefix, $namespace, $viewNamespaces)
    {
        $registry = new ModuleVoltComponentRegistry();
        $registry->registerComponents([
            'path' => $path ?? null,
            'aliasPrefix' => $aliasPrefix.'::',
            'namespace' => $namespace,
            'view_namespaces' => $viewNamespaces ?? ['livewire', 'pages'],
        ]);
    }

    protected function registerComponentDirectory($directory, $namespace, $aliasPrefix = '')
    {
        $filesystem = new Filesystem();

        if (! $filesystem->isDirectory($directory)) {
            return false;
        }

        collect($filesystem->allFiles($directory))
            ->map(function (SplFileInfo $file) use ($namespace) {
                return (string) Str::of($namespace)
                    ->append('\\', $file->getRelativePathname())
                    ->replace(['/', '.php'], ['\\', '']);
            })
            ->filter(function ($class) {
                return is_subclass_of($class, Component::class) && ! (new ReflectionClass($class))->isAbstract();
            })
            ->each(function ($class) use ($namespace, $aliasPrefix) {
                $alias = $aliasPrefix.Str::of($class)
                    ->after($namespace.'\\')
                    ->replace(['/', '\\'], '.')
                    ->explode('.')
                    ->map([Str::class, 'kebab'])
                    ->implode('.');

                if (Str::endsWith($class, ['\Index', '\index'])) {
                    Livewire::component(Str::beforeLast($alias, '.index'), $class);
                }

                Livewire::component($alias, $class);
            });
    }

    public function registerModuleVoltViewFactory()
    {
        if (! class_exists(\Livewire\Volt\Volt::class)) {
            return false;
        }

        $this->app->extend('view', function ($view, $app) {
            $factory = new ModuleVoltViewFactory(
                $app['view.engine.resolver'],
                $app['view.finder'],
                $app['events']
            );

            // Copy existing view paths
            foreach ($view->getFinder()->getPaths() as $path) {
                $factory->getFinder()->addLocation($path);
            }

            // Copy existing hint paths (this fixes the missing hint path issue)
            foreach ($view->getFinder()->getHints() as $namespace => $paths) {
                foreach ((array) $paths as $path) {
                    $factory->addNamespace($namespace, $path);
                }
            }

            $factory->setContainer($app);

            $factory->share('app', $app);

            return $factory;
        });

        \View::clearResolvedInstance('view');
    }
}
