<?php

namespace Mhmiton\LaravelModulesLivewire;

use Illuminate\Support\ServiceProvider;
use Mhmiton\LaravelModulesLivewire\Commands\CacheComponentsCommand;
use Mhmiton\LaravelModulesLivewire\Commands\ClearComponentsCacheCommand;
use Mhmiton\LaravelModulesLivewire\Commands\LivewireMakeCommand;
use Mhmiton\LaravelModulesLivewire\Commands\VoltMakeCommand;

class LaravelModulesLivewireServiceProvider extends ServiceProvider
{
    public function boot(LivewireComponentRegistrar $registrar): void
    {
        $components = $registrar->getComponents();

        foreach ($components as [$alias, $class]) {
            Livewire::component($alias, $class);
        }
    }

    public function register()
    {
        $this->registerComponentRegistrar();

        $this->registerCommands();

        $this->registerPublishables();

        $this->mergeConfigFrom(
            __DIR__ . '/../config/modules-livewire.php',
            'modules-livewire'
        );
    }

    protected function registerComponentRegistrar()
    {
        $this->app->singleton(LivewireComponentRegistrar::class, function ($app) {
            return new LivewireComponentRegistrar($app->make(\Illuminate\Filesystem\Filesystem::class));
        });
    }

    protected function registerCommands()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            LivewireMakeCommand::class,
            VoltMakeCommand::class,
            CacheComponentsCommand::class,
            ClearComponentsCacheCommand::class,
        ]);

        if (method_exists($this, 'optimizes')) {
            $this->optimizes(
                'livewire-modules:cache-components',
                'livewire-modules:clear-components-cache',
                'livewire-modules-components'
            );
        }
    }

    protected function registerPublishables()
    {
        $this->publishes([
            __DIR__ . '/../config/modules-livewire.php' => base_path('config/modules-livewire.php'),
        ], ['modules-livewire-config']);

        $this->publishes([
            __DIR__ . '/Commands/stubs/' => base_path('stubs/modules-livewire'),
        ], ['modules-livewire-stub']);
    }
}
