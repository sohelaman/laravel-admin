<?php

namespace Tests\Controllers;

use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Tests\Models\Image;

class ThumbnailController extends AdminController
{
    protected $title = 'Thumbnails';

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Image());

        $form->image('image1')->thumbnail([
            'small' => [100, 100],
            'cover' => [60, 60, 'fit'],
        ]);

        // The other columns are required by the table.
        $form->saving(function (Form $form) {
            foreach (range(2, 6) as $index) {
                $form->model()->{'image'.$index} = '';
            }
        });

        return $form;
    }
}
