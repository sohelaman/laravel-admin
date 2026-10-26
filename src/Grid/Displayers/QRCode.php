<?php

namespace Encore\Admin\Grid\Displayers;

use Encore\Admin\Facades\Admin;

/**
 * Class QRCode.
 *
 * The code is generated in the browser, the content is not sent to a third party service.
 */
class QRCode extends AbstractDisplayer
{
    protected function addScript()
    {
        Admin::js('/vendor/laravel-admin/qrcode-generator/qrcode.js');

        $script = <<<'SCRIPT'
qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];

$('.grid-column-qrcode').each(function () {
    var $link = $(this);
    var width = parseInt($link.data('width'), 10) || 150;
    var height = parseInt($link.data('height'), 10) || 150;

    var qr = qrcode(0, 'M');
    qr.addData(String($link.data('qrcode')));
    qr.make();

    var cell = Math.max(1, Math.floor(Math.min(width, height) / (qr.getModuleCount() + 4)));
    var $img = $('<img>').attr({src: qr.createDataURL(cell, cell * 2), width: width, height: height});

    $link.popover({
        html: true,
        container: 'body',
        trigger: 'focus',
        content: $('<div>').append($img).html()
    });
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

        $content = e($content);
        $width = (int) $width;
        $height = (int) $height;

        $value = e($this->getValue());

        return <<<HTML
<a href="javascript:void(0);" class="grid-column-qrcode text-muted" data-qrcode="{$content}" data-width="{$width}" data-height="{$height}" tabindex='0'>
    <i class="fa fa-qrcode"></i>
</a>&nbsp;{$value}
HTML;
    }
}
