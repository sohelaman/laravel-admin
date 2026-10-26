<?php

use Encore\Admin\Auth\Database\Permission;
use Illuminate\Support\Facades\Artisan;
use Tests\Models\User;

class ConsoleTest extends TestCase
{
    public function testMakeCommandOutputsCodeForModel()
    {
        $this->artisan('admin:make', ['name' => 'TestUserController', '--model' => User::class, '--output' => true]);

        $output = Artisan::output();

        $this->assertStringContainsString("\$grid->column('username', __('Username'))", $output);
        $this->assertStringContainsString("\$show->field('username', __('Username'))", $output);
        $this->assertStringContainsString("\$form->text('username', __('Username'))", $output);
        $this->assertStringContainsString("\$form->email('email', __('Email'))", $output);
        $this->assertStringContainsString("\$form->mobile('mobile', __('Mobile'))", $output);
        $this->assertStringContainsString("\$form->image('avatar', __('Avatar'))", $output);
        $this->assertStringContainsString("\$form->password('password', __('Password'))", $output);

        // reserved columns are not part of the form
        $this->assertStringNotContainsString("\$form->number('id'", $output);
        $this->assertStringNotContainsString("\$form->datetime('created_at'", $output);
    }

    public function testMakeCommandMapsColumnTypes()
    {
        $this->artisan('admin:make', ['name' => 'TestUserController', '--model' => User::class, '--output' => true]);

        $output = Artisan::output();

        // `test_users.data` is a json column, reported as text by some drivers.
        $this->assertMatchesRegularExpression("/\\\$form->(text|textarea)\\('data', __\\('Data'\\)\\)/", $output);
    }

    public function testMakeCommandWithUnknownModel()
    {
        $this->artisan('admin:make', ['name' => 'TestUserController', '--model' => 'Tests\Models\Missing']);

        $this->assertStringContainsString('Model does not exists', Artisan::output());
    }

    public function testPermissionsAreGeneratedFromTables()
    {
        $this->artisan('admin:permissions', ['--tables' => 'test_tags']);

        $this->assertGreaterThan(0, Permission::where('slug', 'like', '%test_tags%')->count());

        $count = Permission::count();

        // running it again does not create duplicates
        $this->artisan('admin:permissions', ['--tables' => 'test_tags']);

        $this->assertEquals($count, Permission::count());
    }
}
