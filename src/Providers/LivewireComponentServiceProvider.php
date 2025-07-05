<?php

namespace Mhmiton\LaravelModulesLivewire\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Mhmiton\LaravelModulesLivewire\Support\ModuleComponentRegistry;
use Mhmiton\LaravelModulesLivewire\View\ModuleVoltViewFactory;

class LivewireComponentServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerComponents();

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

    protected function registerComponents()
    {
        $registry = app()->get(ModuleComponentRegistry::class);

        foreach ($registry->getComponents() as $component) {
            Livewire::component($component[0], $component[1]);
        }
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
