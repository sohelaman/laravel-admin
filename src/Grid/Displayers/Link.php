<?php

namespace Encore\Admin\Grid\Displayers;

class Link extends AbstractDisplayer
{
    public function display($callback = '', $target = '_blank')
    {
        if ($callback instanceof \Closure) {
            $callback = $callback->bindTo($this->row);
            $href = call_user_func_array($callback, [$this->row]);
        } else {
            $href = $callback ?: $this->value;
        }

        // links are not allowed to run scripts
        if (preg_match('/^\s*(javascript|vbscript|data):/i', $href)) {
            $href = '#';
        }

        return "<a href='".e($href)."' target='".e($target)."'>".e($this->value).'</a>';
    }
}
