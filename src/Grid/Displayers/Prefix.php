<?php

namespace Encore\Admin\Grid\Displayers;

class Prefix extends AbstractDisplayer
{
    public function display($prefix = null, $delimiter = '&nbsp;')
    {
        if ($prefix instanceof \Closure) {
            $prefix = $prefix->call($this->row, $this->getValue(), $this->getColumn()->getOriginal());
        }

        $value = e($this->getValue());

        return <<<HTML
{$prefix}{$delimiter}{$value}
HTML;
    }
}
