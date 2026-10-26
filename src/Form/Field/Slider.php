<?php

namespace Encore\Admin\Form\Field;

use Encore\Admin\Form\Field;

class Slider extends Field
{
    protected static $css = [
        '/vendor/laravel-admin/AdminLTE/plugins/ionslider/ion.rangeSlider.min.css',
    ];

    protected static $js = [
        '/vendor/laravel-admin/AdminLTE/plugins/ionslider/ion.rangeSlider.min.js',
    ];

    protected $options = [
        'type'             => 'single',
        'prettify_enabled' => false,
        'grid'             => true,
        'skin'             => 'round',
    ];

    /**
     * Options of ion.rangeSlider 1.x and their name since 2.x.
     *
     * @var array
     */
    protected $legacyOptions = [
        'prettify' => 'prettify_enabled',
        'hasGrid'  => 'grid',
    ];

    public function render()
    {
        foreach ($this->legacyOptions as $legacy => $current) {
            if (array_key_exists($legacy, $this->options)) {
                $this->options[$current] = $this->options[$legacy];
                unset($this->options[$legacy]);
            }
        }

        $option = json_encode($this->options);

        $this->script = "$('{$this->getElementClassSelector()}').ionRangeSlider($option);";

        return parent::render();
    }
}
