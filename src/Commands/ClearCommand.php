<?php

namespace Mhmiton\LaravelModulesLivewire\Commands;

use Illuminate\Console\Command;
use Mhmiton\LaravelModulesLivewire\Support\ModuleComponentRegistry;

final class ClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:components-clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes the component cache file';

    public function handle(ModuleComponentRegistry $registry): int
    {
        $registry->clearCache();

        $this->info('Component cache file cleared!');

        return 0;
    }
}
