<?php

namespace Tests\Controllers;

use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Grid;
use Tests\Models\User;

class DisplayerController extends AdminController
{
    protected $title = 'Displayers';

    protected function grid()
    {
        $grid = new Grid(new User());

        $grid->column('id');
        $grid->column('username')->copyable();
        $grid->column('email')->qrcode();
        $grid->column('mobile')->link();
        $grid->column('avatar')->label();
        $grid->column('password')->badge();

        $grid->column('html')->display(function () {
            return new \Illuminate\Support\HtmlString('<b class="trusted">ok</b>');
        })->label();

        $grid->column('created_at', 'Avatar')->gravatar(40);

        $grid->disableFilter();

        return $grid;
    }
}
