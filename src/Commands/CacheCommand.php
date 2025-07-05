<?php

namespace Mhmiton\LaravelModulesLivewire\Commands;

use Illuminate\Console\Command;
use Mhmiton\LaravelModulesLivewire\Support\ModuleComponentRegistry;

final class CacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:components-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Caches the livewire components of all modules';

    public function handle(ModuleComponentRegistry $registry): int
    {
        $registry->cacheComponents();

        $this->info('Cache file generated successfully!');

        return 0;
    }
}
