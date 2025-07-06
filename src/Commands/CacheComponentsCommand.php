<?php

namespace Mhmiton\LaravelModulesLivewire\Commands;

use Illuminate\Console\Command;
use Mhmiton\LaravelModulesLivewire\LivewireComponentRegistrar;

class CacheComponentsCommand extends Command
{
    protected $signature = 'livewire-modules:cache-components';

    protected $description = 'Scan and cache Livewire components from modules';

    public function handle(LivewireComponentRegistrar $registrar)
    {
        $registrar->cacheComponents();

        $this->info('Livewire module components cached successfully.');
    }
}
