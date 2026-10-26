<?php

use Encore\Admin\Auth\Database\Administrator;
use Tests\Controllers\SideEffect;

class HandleControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->be(Administrator::first(), 'admin');

        SideEffect::$created = false;
    }

    public function testOnlyActionsCanBeResolved()
    {
        $this->post('admin/_handle_action_', ['_action' => SideEffect::class]);

        $this->assertResponseStatus(500);
        $this->assertFalse(SideEffect::$created);
    }

    public function testOnlyFormsCanBeResolved()
    {
        $this->post('admin/_handle_form_', ['_form_' => SideEffect::class]);

        $this->assertResponseStatus(500);
        $this->assertFalse(SideEffect::$created);
    }

    public function testOnlySelectablesCanBeCreated()
    {
        $this->get('admin/_handle_selectable_?selectable='.urlencode(SideEffect::class));

        $this->assertResponseStatus(404);
        $this->assertFalse(SideEffect::$created);
    }

    public function testOnlyRenderablesCanBeCreated()
    {
        $this->get('admin/_handle_renderable_?renderable='.urlencode(SideEffect::class));

        $this->assertResponseStatus(404);
        $this->assertFalse(SideEffect::$created);
    }

    public function testTheClassNameIsNotReflected()
    {
        $this->get('admin/_handle_selectable_?selectable='.urlencode('<script>alert(1)</script>'));

        $this->assertResponseStatus(404);
        $this->assertStringNotContainsString('<script>alert(1)', $this->response->getContent());

        $this->get('admin/_handle_renderable_?renderable='.urlencode('<script>alert(1)</script>'));

        $this->assertResponseStatus(404);
        $this->assertStringNotContainsString('<script>alert(1)', $this->response->getContent());
    }
}
