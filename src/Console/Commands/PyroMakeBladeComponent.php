<?php

namespace BehaviorLab\BladeSupportModule\Console\Commands;

use Anomaly\BlocksModule\Type\TypeModel;
use Anomaly\Streams\Platform\Addon\AddonCollection;
use Anomaly\Streams\Platform\Assignment\AssignmentModel;
use Anomaly\Streams\Platform\Assignment\Contract\AssignmentInterface;
use Anomaly\Streams\Platform\Stream\Contract\StreamInterface;
use Anomaly\Streams\Platform\Stream\Contract\StreamRepositoryInterface;
use DkBehavior\ApiModule\Service\AssignmentComposer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ComponentMakeCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Str;

class PyroMakeBladeComponent extends ComponentMakeCommand
{
    private AddonCollection $addons;
    private $theme;
    private TypeModel|null $blockTypeModel = null;
    private array $blockFields = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pyro:blade-component {name? : The name of the blade component.}
    {--theme= : The theme to create a blade component for.}
    {--style} {--type}
    {--inline} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new blade view component class for the theme';

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
        Filesystem      $files,
        AddonCollection $addons
    )
    {
        parent::__construct($files);
        $this->addons = $addons;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!($this->option('theme') && $this->theme = $this->addons->themes->where('namespace', $this->option('theme'))->first())) {
            $themeName = $this->choice(
                'Which theme do you want to create a blade component for?',
                $this->addons->themes->where('admin', false)->where('vendor', '!=', 'pyrocms')->pluck('namespace')->toArray(),
                0
            );
            $this->theme = $this->addons->themes->where('namespace', $themeName)->first();
        }
        if ($this->option('type') && in_array(Str::studly($this->option('type')), ['Component', 'Block', 'Page'])) {
            $this->type = Str::studly($this->option('type'));
        } else {
            $this->type = $this->choice(
                'What component type do you want to create?',
                ['Component', 'Block', 'Page'],
                0
            );
        }

        if ($this->type === 'Block') {
            $blockTypes = TypeModel::all()->pluck('slug')->toArray();
            if ($this->argument('name') && in_array($this->argument('name'), $blockTypes)) {
                $blockType = $this->argument('name');
            } else {
                foreach ($blockTypes as $idx => $blockType) {
                    /** @var TypeModel $blockTypeModel */
                    $blockTypeModel = TypeModel::where('slug', $blockType)->first();
                    $blockTypes[$idx] = $blockTypeModel->getSlug() . ' (' . $blockTypeModel->getName() . ')';
                }
                $blockType = $this->choice(
                    'What block type do you want to create view for?',
                    $blockTypes
                );
            }
            $blockType = explode(' (', $blockType)[0];
            $this->input->setArgument('name', Str::studly($blockType));

            $this->blockTypeModel = TypeModel::where('slug', $blockType)->first();

            $streams = app(StreamRepositoryInterface::class);

            /* @var StreamInterface $stream */
            $stream = $streams->findBySlugAndNamespace($this->blockTypeModel->getSlug() . '_blocks', 'blocks');

            /* @var AssignmentInterface $assignment */
            foreach ($stream->getAssignments() as $assignment) {
                $this->blockFields[] = $assignment;
            }
        }

        if (parent::handle() === false && !$this->option('force')) {
            return false;
        }

        if (!$this->option('inline')) {
//            $this->writeView();
            $this->writeStylesheet();
        }
    }

    /**
     * Get the first view directory path from the application configuration.
     *
     * @param string $path
     * @return string
     */
    protected function viewPath($path = '')
    {
        $viewPath = theme_path('resources/views/' . Str::lower($this->type) . 's');

        return $viewPath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
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
     * Write the view for the component.
     *
     * @return void
     */
    protected function writeView()
    {
        $path = $this->viewPath(
            str_replace('.', '/', $this->getView()) . '.blade.php'
        );

        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }

        if ($this->files->exists($path) && !$this->option('force')) {
            $this->error('Pyro blade view already exists!');

            return;
        }

        $viewContent = '<div'.($this->type[0] === 'b' ? ' id="{{$block->type->id}}-{{$block->id}}"' : '') .' class="' . str_replace('.', '-', Str::lower($this->type[0]) . '.' . $this->getView()) . '">' . "\n";
        /* @var AssignmentInterface $blockField */
        foreach ($this->blockFields as $blockField) {
            $viewContent .= '    {{-- (Field type: ' . $blockField->getFieldTypeValue() . ') --}}' . "\n";
            $viewContent .= '    Field ' . $blockField->getFieldName() . ': {{ $block->' . $blockField->getFieldSlug() . ' }}<br>' . "\n";
        }
        $viewContent .= '</div>';

        file_put_contents($path, $viewContent);
    }

    /**
     * Write the view for the component.
     *
     * @return void
     */
    protected function writeStylesheet()
    {
        $path = $this->stylePath(
            str_replace('.', '/', $this->getView()) . '.scss'
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
            '.' . str_replace('.', '-', Str::lower($this->type[0]) . '.' . $this->getView()) . ' {
  // Nest all styles for the ' . Str::lower($this->type) . ' here
}');
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
            'view(\'theme::' . Str::lower($this->type) . 's.' . $this->getView() . '\')',
            $stub
        );
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\View\\' . $this->type . 's';
    }

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return theme_path('src') . '/' . str_replace('\\', '/', $name) . '.php';
    }
}
