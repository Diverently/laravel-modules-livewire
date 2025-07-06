<?php

namespace Mhmiton\LaravelModulesLivewire\Commands;

use Illuminate\Console\Command;
use Mhmiton\LaravelModulesLivewire\LivewireComponentRegistrar;

class ClearComponentsCacheCommand extends Command
{
    protected $signature = 'livewire-modules:clear-components-cache';

    protected $description = 'Clear the cached Livewire module components.';

    public function handle(LivewireComponentRegistrar $registrar)
    {
        $registrar->clearCache();

        $this->info('Livewire module component cache cleared.');
    }
}
