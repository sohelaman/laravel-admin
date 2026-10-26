<?php

namespace Tests\Controllers;

class SideEffect
{
    public static $created = false;

    public function __construct()
    {
        static::$created = true;
    }

    public function handle()
    {
    }

    public function render()
    {
        return 'rendered';
    }
}
