<?php

namespace ConductLab\BladeSupportModule\Console\Commands;

use Anomaly\Streams\Platform\Addon\AddonCollection;
use Anomaly\Streams\Platform\Addon\AddonServiceProvider;
use Anomaly\Streams\Platform\Support\Writer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Livewire\Commands\ComponentParser;
use Livewire\Commands\MakeCommand;
use Livewire\Livewire;

class PyroMakeLivewireComponent extends MakeCommand
{
//    private AddonCollection $addons;
    private $theme;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pyro:livewire-component {name : The name of the livewire component.}
    {--theme= : The theme to create a livewire component for.}
    {--inline} {--force} {--test}
    {--stub= : If you have several stubs, stored in subfolders }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new livewire view component class for the theme';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Component';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        private Filesystem      $files,
        private AddonCollection $addons
    )
    {
        parent::__construct();
//        $this->addons = $addons;

        $this->theme = $this->addons->themes->active('standard');
    }

    /**
     * Execute the console command.
     *
     * @return int|void
     */
    public function handle()
    {
        /** @var Writer $writer */
        $writer = app(Writer::class);

        /** @var AddonServiceProvider $sp */
        $sp = app($this->theme->getServiceProvider());

        $spReflector = new \ReflectionClass($sp);
        $spFileName = $spReflector->getFileName();

        if (!property_exists($sp, 'livewireComponents')) {
            $writer->append(
                $spFileName,
                '/protected array \$bindings = \[(.|\s)*];/miU',
                "\n\n     /**
      * The addons livewire components.
      *
      * @type array
      */
     protected \$livewireComponents = [
     ];"
            );

            $writer->append(
                $spFileName,
                '/public function register\(\)\s*\{/i',
                '
        foreach ($this->livewireComponents as $alias =>  $viewClass) {
            Livewire::component($alias, $viewClass);
        }');
        }

        Config::set('livewire.class_namespace', __NAMESPACE__ . '\\Http\\Livewire\\Components');
        Config::set('livewire.view_path', theme_path('resources/views/livewire/components'));

        /** @var LivewireComponentParser parser */
        $this->parser = new LivewireComponentParser(
            $spReflector->getNamespaceName() . '\\Http\\Livewire\\Components',
            theme_path('src/Http/Livewire/Components/'),
            theme_path('resources/views/livewire/components'),
            $this->argument('name'),
            $this->option('stub')
        );

        if (!$this->isClassNameValid($name = $this->parser->className())) {
            $this->line("<options=bold,reverse;fg=red> WHOOPS! </> 😳 \n");
            $this->line("<fg=red;options=bold>Class is invalid:</> {$name}");

            return;
        }

        if ($this->isReservedClassName($name)) {
            $this->line("<options=bold,reverse;fg=red> WHOOPS! </> 😳 \n");
            $this->line("<fg=red;options=bold>Class is reserved:</> {$name}");

            return;
        }

        $force = $this->option('force');
        $inline = $this->option('inline');
        $test = $this->option('test');


        $showWelcomeMessage = $this->isFirstTimeMakingAComponent();

        $class = $this->createClass($force, $inline);
        $view = $this->createView($force, $inline);
        if (!$inline) {
            $this->writeStylesheet();
        }

        if ($test) {
            $test = $this->createTest($force);
        }

//        $this->refreshComponentAutodiscovery();
//        dd($view);

        if($class || $view) {
            $this->line("<options=bold,reverse;fg=green> COMPONENT CREATED </> 🤙\n");
            $class && $this->line("<options=bold;fg=green>CLASS:</> {$this->parser->relativeClassPath()}");

            if (! $inline) {
                $view && $this->line("<options=bold;fg=green>VIEW:</>  {$this->parser->relativeViewPath()}");
            }

            if ($test) {
                $test && $this->line("<options=bold;fg=green>TEST:</>  {$this->parser->relativeTestPath()}");
            }

            $writer->append(
                $spFileName,
                '/public array \$livewireComponents = \[\n/i',
                "        '{$this->parser->viewName()}' => \\{$this->parser->classNamespace()}\\{$this->parser->className()}::class,\n");

            if ($showWelcomeMessage && ! app()->runningUnitTests()) {
                $this->writeWelcomeMessage();
            }
        }
    }

    /**
     * Write the view for the component.
     *
     * @return void
     */
//    protected function writeView()
//    {
//        parent::writeView();
////        $path = $this->viewPath(
////            str_replace('.', '/', 'components.' . $this->getView()) . '.blade.php'
////        );
////        $this->info($path);
//    }

    /**
     * Write the view for the component.
     *
     * @return void
     */
    protected function writeStylesheet()
    {
        $path = $this->stylePath(
            str_replace('.', '/', $this->parser->component) . '.scss'
        );

        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }

        if ($this->files->exists($path) && !$this->option('force')) {
            $this->error('Stylesheet already exists!');

            return;
        }

        file_put_contents(
            $path,
            '.' . str_replace('.', '-', Str::lower($this->type[0]) . '.' . $this->parser->component) . ' {
  // Nest all styles for the ' . Str::lower($this->type) . ' here
}');
    }

    /**
     * Get the first view directory path from the application configuration.
     *
     * @param string $path
     * @return string
     */
    protected function stylePath($path = ''): string
    {
        $stylePath = theme_path('resources/assets/styles/' . Str::lower($this->type) . 's');

        return $stylePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the first view directory path from the application configuration.
     *
     * @param string $path
     * @return string
     */
    protected function viewPath($path = '')
    {
        $viewPath = theme_path('resources/views');

        return $viewPath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Build the class with the given name.
     *
     * @param string $name
     * @return string
     */
    protected function buildClass($name)
    {

        $stub = $this->files->get($this->getStub());

        $stub = $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);

        if ($this->option('inline')) {
            return str_replace(
                ['DummyView', '{{ view }}'],
                "<<<'blade'\n<div>\n    <!-- " . Inspiring::quote() . " -->\n</div>\nblade",
                $stub
            );
        }

        return str_replace(
            ['DummyView', '{{ view }}'],
            'view(\'theme::livewire.components.' . $this->getView() . '\')',
            $stub
        );
    }

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return theme_path('src') . '/' . str_replace('\\', '/', $name) . '.php';
    }
}
