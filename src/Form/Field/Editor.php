<?php

namespace Encore\Admin\Form\Field;

use Encore\Admin\Form\Field;

class Editor extends Field
{
    protected static $css = [
        '/vendor/laravel-admin/summernote/summernote-lite.min.css',
    ];

    protected static $js = [
        '/vendor/laravel-admin/summernote/summernote-lite.min.js',
    ];

    /**
     * Options of Summernote, https://summernote.org/deep-dive/.
     *
     * @var array
     */
    protected $options = [
        'height' => 300,
    ];

    public function render()
    {
        $options = json_encode($this->options);

        $this->script = "$('#{$this->id}').summernote({$options});";

        return parent::render();
    }
}
