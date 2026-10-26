<?php

class AuthTest extends TestCase
{
    public function testLoginPage()
    {
        $this->visit('admin/auth/login')
            ->see('login');
    }

    public function testVisitWithoutLogin()
    {
        $this->visit('admin')
            ->dontSeeIsAuthenticated('admin')
            ->seePageIs('admin/auth/login');
    }

    public function testLogin()
    {
        $credentials = ['username' => 'admin', 'password' => 'admin'];

        $this->visit('admin/auth/login')
            ->see('login')
            ->submitForm('Login', $credentials)
            ->see('dashboard')
            ->seeCredentials($credentials, 'admin')
            ->seeIsAuthenticated('admin')
            ->seePageIs('admin')
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

        $this
            ->see('<span>Admin</span>')
            ->see('<span>Users</span>')
            ->see('<span>Roles</span>')
            ->see('<span>Permission</span>')
            ->see('<span>Operation log</span>')
            ->see('<span>Menu</span>');
    }

    public function testLoginWithWrongPassword()
    {
        $this->visit('admin/auth/login')
            ->submitForm('Login', ['username' => 'admin', 'password' => 'wrong'])
            ->seePageIs('admin/auth/login')
            ->see('These credentials do not match our records.')
            ->dontSeeIsAuthenticated('admin');
    }

    public function testLoginIsThrottled()
    {
        foreach (range(1, 5) as $attempt) {
            $this->visit('admin/auth/login')
                ->submitForm('Login', ['username' => 'admin', 'password' => 'wrong'])
                ->see('These credentials do not match our records.');
        }

        // The correct password is refused while the user is locked out.
        $this->visit('admin/auth/login')
            ->submitForm('Login', ['username' => 'admin', 'password' => 'admin'])
            ->seePageIs('admin/auth/login')
            ->see('Too many login attempts')
            ->dontSeeIsAuthenticated('admin');
    }

    public function testLogout()
    {
        $this->visit('admin/auth/logout')
            ->seePageIs('admin/auth/login')
            ->dontSeeIsAuthenticated('admin');
    }
}
