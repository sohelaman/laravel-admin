<?php

namespace Encore\Admin\Grid\Displayers;

use Encore\Admin\Facades\Admin;

/**
 * Class QRCode.
 */
class QRCode extends AbstractDisplayer
{
    protected function addScript()
    {
        $script = <<<'SCRIPT'
$('.grid-column-qrcode').popover({
    html: true,
    container: 'body',
    trigger: 'focus'
});
SCRIPT;

        Admin::script($script);
    }

    public function display($formatter = null, $width = 150, $height = 150)
    {
        $this->addScript();

        $content = $this->getColumn()->getOriginal();

        if ($formatter instanceof \Closure) {
            $content = call_user_func($formatter, $content, $this->row);
        }

        $img = sprintf(
            "<img src='https://api.qrserver.com/v1/create-qr-code/?size=%dx%d&data=%s' style='height:%dpx;width:%dpx;'/>",
            $width,
            $height,
            rawurlencode($content),
            $height,
            $width
        );

        // the image is the content of a popover and is written in an html attribute
        $img = e($img);

        $value = e($this->getValue());

        return <<<HTML
<a href="javascript:void(0);" class="grid-column-qrcode text-muted" data-content="{$img}" data-toggle='popover' tabindex='0'>
    <i class="fa fa-qrcode"></i>
</a>&nbsp;{$value}
HTML;
    }
}
