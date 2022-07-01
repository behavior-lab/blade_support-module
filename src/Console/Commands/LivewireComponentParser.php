<?php

namespace ConductLab\BladeSupportModule\Console\Commands;

use Illuminate\Support\Str;
use Livewire\Commands\ComponentParser;
use function Livewire\str;

class LivewireComponentParser extends ComponentParser
{
    public $component;
    public function __construct($classNamespace, $baseClassPath, $viewPath, $rawCommand, $stubSubDirectory = '')
    {
        $this->baseClassNamespace = $classNamespace;
        $this->baseTestNamespace = 'Tests\Feature\Livewire';

        $classPath = $baseClassPath;//static::generatePathFromNamespace($classNamespace);
        $testPath = static::generateTestPathFromNamespace($this->baseTestNamespace);

        $this->baseClassPath = rtrim($classPath, DIRECTORY_SEPARATOR).'/';
        $this->baseViewPath = rtrim($viewPath, DIRECTORY_SEPARATOR).'/';
        $this->baseTestPath = rtrim($testPath, DIRECTORY_SEPARATOR).'/';

        if(! empty($stubSubDirectory) && str($stubSubDirectory)->startsWith('..')) {
            $this->stubDirectory = rtrim(str($stubSubDirectory)->replaceFirst('..' . DIRECTORY_SEPARATOR, ''), DIRECTORY_SEPARATOR).'/';
        } else {
            $this->stubDirectory = rtrim('stubs'.DIRECTORY_SEPARATOR.$stubSubDirectory, DIRECTORY_SEPARATOR).'/';
        }

        $directories = preg_split('/[.\/(\\\\)]+/', $rawCommand);

        $camelCase = str(array_pop($directories))->camel();
        $kebabCase = str($camelCase)->kebab();

        $this->component = $kebabCase;
        $this->componentClass = str($this->component)->studly();

        $this->directories = array_map([Str::class, 'studly'], $directories);
    }

    public function viewName()
    {
        return collect()
            ->push('theme::livewire.components')
            ->filter()
            ->concat($this->directories)
            ->map([Str::class, 'kebab'])
            ->push($this->component)
            ->implode('.')
            ;
    }

    public static function generatePathFromNamespace($namespace)
    {
        $name = str($namespace)->finish('\\')->replaceFirst(app()->getNamespace(), '');
        return app('path').'/'.str_replace('\\', '/', $name);
    }

}
