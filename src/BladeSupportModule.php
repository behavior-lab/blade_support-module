<?php namespace ConductLab\BladeSupportModule;

use Anomaly\Streams\Platform\Addon\Module\Module;

class BladeSupportModule extends Module
{

    /**
     * The navigation display flag.
     *
     * @var bool
     */
    protected $navigation = false;

    /**
     * The addon icon.
     *
     * @var string
     */
    protected $icon = 'fa fa-puzzle-piece';

    /**
     * The module sections.
     *
     * @var array
     */
    protected $sections = [];

}
