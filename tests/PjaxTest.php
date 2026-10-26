<?php

use Encore\Admin\Auth\Database\Administrator;

class PjaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->be(Administrator::first(), 'admin');
    }

    public function testPjaxRequestOnlyReturnsTheContainer()
    {
        $this->get('admin/auth/users', ['X-PJAX' => 'true', 'X-PJAX-Container' => '#pjax-container']);

        $this->assertResponseOk();

        $content = $this->response->getContent();

        $this->assertStringStartsWith('<title>', $content);
        $this->assertStringContainsString('Administrator', $content);
        $this->assertStringNotContainsString('<html', $content);
        $this->assertStringNotContainsString('main-sidebar', $content);

        $this->assertEquals('/admin/auth/users', $this->response->headers->get('X-PJAX-URL'));
    }

    public function testRegularRequestReturnsTheWholePage()
    {
        $this->get('admin/auth/users');

        $this->assertResponseOk();
        $this->assertStringContainsString('<html', $this->response->getContent());
    }

    public function testPjaxRequestWithUnknownContainer()
    {
        $this->get('admin/auth/users', ['X-PJAX' => 'true', 'X-PJAX-Container' => '#unknown']);

        // The container does not exist, the page can not be filtered.
        $this->assertResponseStatus(200);
        $this->assertStringContainsString('<html', $this->response->getContent());
    }
}
