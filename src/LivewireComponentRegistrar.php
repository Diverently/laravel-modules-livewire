<?php

namespace Mhmiton\LaravelModulesLivewire;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Nwidart\Modules\Facades\Module;

class LivewireComponentRegistrar
{
    protected Filesystem $filesystem;
    protected string $cacheFile;

    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
        $this->cacheFile = storage_path('framework/livewire-modules-components.php');
    }

    /**
     * Get all components either from cache or by scanning modules.
     *
     * @return array<int, array{0:string,1:string}> Array of [$alias, $class]
     */
    public function getComponents(): array
    {
        if ($this->filesystem->exists($this->cacheFile)) {
            return $this->filesystem->getRequire($this->cacheFile);
        }

        return $this->getComponentsFromFilesystem();
    }

    public function getComponentsFromFilesystem()
    {
        $allComponents = [];

        $modules = Module::toCollection();

        foreach ($modules as $module) {
            $directory = $module->getExtraPath('Livewire');
            $relativePath = Str::after($directory, base_path());
            $namespace = Str::replace('/', '\\', $relativePath);
            $aliasPrefix = "{$module->getLowerName()}::";

            $components = $this->getComponentsFromDirectory($directory, $namespace, $aliasPrefix);

            $allComponents = array_merge($allComponents, $components->toArray());
        }

        return $allComponents;
    }

    public function cacheComponents(): void
    {
        $components = $this->getComponentsFromFilesystem();

        $cacheDir = dirname($this->cacheFile);
        if (!$this->filesystem->isDirectory($cacheDir)) {
            $this->filesystem->makeDirectory($cacheDir, 0755, true);
        }

        $export = var_export($components, true);
        $this->filesystem->put(
            $this->cacheFile,
            "<?php\n\nreturn {$export};\n"
        );
    }

    public function clearCache(): void
    {
        if ($this->filesystem->exists($this->cacheFile)) {
            $this->filesystem->delete($this->cacheFile);
        }
    }

    /**
     * Get components from directory.
     *
     * @param string $directory
     * @param string $namespace
     * @param string $aliasPrefix
     *
     * @return Collection
     */
    protected function getComponentsFromDirectory(string $directory, string $namespace, string $aliasPrefix): Collection
    {
        $components = collect();

        if (!$this->filesystem->isDirectory($directory)) {
            return $components;
        }

        foreach ($this->filesystem->allFiles($directory) as $file) {
            $class = Str::of($namespace)
                ->append("\\{$file->getRelativePathname()}")
                ->replace(['/', '.php'], ['\\', ''])
                ->toString();

            if (is_subclass_of($class, Component::class)) {
                $components[] = $this->getSingleComponent($class, $namespace, $aliasPrefix);
            }
        }

        return $components;
    }

    /**
     * Get livewire single component.
     *
     * @param string $class
     * @param string $namespace
     * @param string $aliasPrefix
     *
     * @return array
     */
    private function getSingleComponent(string $class, string $namespace, string $aliasPrefix): array
    {
        $alias = $aliasPrefix . Str::of($class)
            ->after($namespace . '\\')
            ->replace(['/', '\\'], '.')
            ->explode('.')
            ->map([Str::class, 'kebab'])
            ->implode('.');

        if (Str::endsWith($class, ['\Index', '\index'])) {
            $alias = Str::beforeLast($alias, '.index');
        }

        if (Str::startsWith($class, '\\')) {
            $class = Str::after($class, '\\');
        }

        return [$alias, $class];
    }
}
