<?php

use Encore\Admin\Auth\Database\Administrator;
use Tests\Models\Profile;
use Tests\Models\User;

class GridDisplayerTest extends TestCase
{
    protected $payload = '<script>alert(2)</script>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->be(Administrator::first(), 'admin');

        $user = User::factory()->create([
            'username' => 'x" onmouseover="alert(1)" data-x="',
            'email'    => "a'b&data=".$this->payload,
            'mobile'   => 'javascript:alert(3)',
            'avatar'   => $this->payload,
            'password' => '<img src=x onerror=alert(4)>',
        ]);

        $user->profile()->save(Profile::factory()->make());

        $this->visit('admin/displayers');
    }

    public function testValuesAreNotInterpretedAsHtml()
    {
        $table = $this->crawler()->filter('table.table');

        $this->assertCount(0, $table->filter('script'));
        $this->assertCount(0, $table->filter('img[onerror]'));
        $this->assertCount(0, $table->filter('[onmouseover]'));
    }

    public function testValuesAreDisplayedAsText()
    {
        $table = $this->crawler()->filter('table.table');

        // label and badge
        $this->assertStringContainsString($this->payload, $table->filter('span.label')->text());
        $this->assertStringContainsString('<img src=x onerror=alert(4)>', $table->filter('span.badge')->text());
    }

    public function testHtmlStringIsNotEscaped()
    {
        $this->assertCount(1, $this->crawler()->filter('table.table span.label b.trusted'));
    }

    public function testCopyableKeepsTheContentInTheAttribute()
    {
        $link = $this->crawler()->filter('a.grid-column-copyable');

        $this->assertSame('x" onmouseover="alert(1)" data-x="', $link->attr('data-content'));
    }

    public function testQrCodeDataIsEncoded()
    {
        $popover = $this->crawler()->filter('a.grid-column-qrcode')->attr('data-content');

        $this->assertStringContainsString(rawurlencode("a'b&data=".$this->payload), $popover);
        $this->assertStringNotContainsString($this->payload, $popover);
    }

    public function testLinkDoesNotRunScripts()
    {
        $link = $this->crawler()->filter('table.table td a[target=_blank]');

        $this->assertSame('#', $link->attr('href'));
    }
}
