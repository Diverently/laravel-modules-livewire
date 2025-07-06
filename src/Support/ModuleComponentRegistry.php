<?php

namespace Mhmiton\LaravelModulesLivewire\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Livewire\Component;
use Mhmiton\LaravelModulesLivewire\Support\ModuleVoltComponentRegistry;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

class ModuleComponentRegistry
{
    private Filesystem $filesystem;

    private string $cachePath;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->cachePath = $this->getCachePath();
    }

    public function getComponents()
    {
        if ($this->filesystem->exists($this->cachePath)) {
            return $this->filesystem->getRequire($this->cachePath);
        }

        return $this->getUncachedComponents();
    }

    public function getUncachedComponents()
    {
        return [
            ...$this->getModuleComponents(),
            ...$this->getCustomModuleComponents()
        ];
    }

    public function cacheComponents()
    {
        if (! is_writable($dirname = dirname($this->cachePath))) {
            throw new Exception("The {$dirname} directory must be present and writable.");
        }

        $this->filesystem->replace(
            $this->cachePath,
            '<?php return '.var_export($this->getUncachedComponents(), true).';',
        );
    }

    public function clearCache()
    {
        return $this->filesystem->delete($this->cachePath);
    }

    protected function getCachePath()
    {
        return Str::replaceLast('services.php', 'modules-components.php', app()->getCachedServicesPath());
    }

    protected function getModuleComponents()
    {
        $modules = \Nwidart\Modules\Facades\Module::toCollection();

        $modulesLivewireNamespace = config('modules-livewire.namespace', 'Livewire');

        return $modules
            ->map(function ($module) use ($modulesLivewireNamespace) {
                $directory = (string) Str::of($module->getAppPath())
                    ->append('/' . $modulesLivewireNamespace)
                    ->replace(['\\'], '/');

                $moduleNamespace = method_exists($module, 'getNamespace')
                    ? $module->getNamespace()
                    : config('modules.namespace', 'Modules');

                $namespace = $moduleNamespace . '\\' . $module->getName() . '\\' . $modulesLivewireNamespace;

                $components = $this->getComponentsFromDirectory($directory, $namespace, $module->getLowerName() . '::');

                if (class_exists(\Livewire\Volt\Volt::class)) {
                    $voltComponents = $this->getVoltComponents(
                        $module->getPath(),
                        $module->getLowerName(),
                        $namespace,
                        config('modules-livewire.volt_view_namespaces')
                    );
                    $components = $components->merge($voltComponents);
                }

                return $components;
            })
            ->flatten(1)
            ->toArray();
    }

    protected function getCustomModuleComponents()
    {
        $modules = collect(config('modules-livewire.custom_modules', []));

        return $modules
            ->map(function ($module, $moduleName) {
                $moduleLivewireNamespace = $module['namespace'] ?? config('modules-livewire.namespace', 'Livewire');

                $directory = (string) Str::of($module['path'] ?? '')
                    ->append('/' . $moduleLivewireNamespace)
                    ->replace(['\\'], '/');

                $namespace = ($module['module_namespace'] ?? $moduleName) . '\\' . $moduleLivewireNamespace;

                $lowerName = $module['name_lower'] ?? strtolower($moduleName);

                $components = $this->getComponentsFromDirectory($directory, $namespace, $lowerName . '::');

                if (class_exists(\Livewire\Volt\Volt::class)) {
                    $voltComponents = $this->getVoltComponents($module['path'], $lowerName, $namespace, $module['volt_view_namespaces']);
                    $components = $components->merge($voltComponents);
                }

                return $components;
            })
            ->flatten(1)
            ->toArray();
    }

    protected function getVoltComponents($path, $aliasPrefix, $namespace, $viewNamespaces)
    {
        $registry = new ModuleVoltComponentRegistry();
        return $registry->getComponents([
            'path' => $path ?? null,
            'aliasPrefix' => $aliasPrefix . '::',
            'namespace' => $namespace,
            'view_namespaces' => $viewNamespaces ?? ['livewire', 'pages'],
        ]);
    }

    protected function getComponentsFromDirectory($directory, $namespace, $aliasPrefix = '')
    {
        $filesystem = new Filesystem();

        if (! $filesystem->isDirectory($directory)) {
            return collect();
        }

        return collect($filesystem->allFiles($directory))
            ->map(function (SplFileInfo $file) use ($namespace) {
                return (string) Str::of($namespace)
                    ->append('\\', $file->getRelativePathname())
                    ->replace(['/', '.php'], ['\\', '']);
            })
            ->filter(function ($class) {
                return is_subclass_of($class, Component::class) && ! (new ReflectionClass($class))->isAbstract();
            })
            ->map(function ($class) use ($namespace, $aliasPrefix) {
                $alias = $aliasPrefix . Str::of($class)
                    ->after($namespace . '\\')
                    ->replace(['/', '\\'], '.')
                    ->explode('.')
                    ->map([Str::class, 'kebab'])
                    ->implode('.');

                if (Str::endsWith($class, ['\Index', '\index'])) {
                    return [Str::beforeLast($alias, '.index'), $class];
                }

                return [$alias, $class];
            });
    }
}
