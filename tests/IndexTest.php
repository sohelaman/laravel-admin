<?php

use Encore\Admin\Auth\Database\Administrator;

class IndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->be(Administrator::first(), 'admin');
    }

    public function testIndex()
    {
        $this->visit('admin/')
            ->see('Dashboard')
            ->see('Description...')

            ->see('Environment')
            ->see('PHP version')
            ->see('Laravel version')

            ->see('Available extensions')
            ->seeLink('laravel-admin-ext/helpers', 'https://github.com/laravel-admin-extensions/helpers')
            ->seeLink('laravel-admin-ext/backup', 'https://github.com/laravel-admin-extensions/backup')
            ->seeLink('laravel-admin-ext/media-manager', 'https://github.com/laravel-admin-extensions/media-manager')

            // The dependency list is read from the composer.json of the host application.
            ->see('Dependencies');
    }

    public function testClickMenu()
    {
        $this->visit('admin/')
            ->click('Users')
            ->seePageis('admin/auth/users')
            ->click('Roles')
            ->seePageis('admin/auth/roles')
            ->click('Permission')
            ->seePageis('admin/auth/permissions')
            ->click('Menu')
            ->seePageis('admin/auth/menu')
            ->click('Operation log')
            ->seePageis('admin/auth/logs');
    }
}
