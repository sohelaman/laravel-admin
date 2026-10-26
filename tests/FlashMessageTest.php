<?php

use Encore\Admin\Auth\Database\Administrator;
use PHPUnit\Framework\Attributes\DataProvider;

class FlashMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->be(Administrator::first(), 'admin');
    }

    /**
     * Simulate a session serialized as json, which is the default of Laravel 13.
     *
     * @param string $key
     */
    protected function roundTripThroughJson($key)
    {
        $session = $this->app['session.store'];

        $session->put($key, json_decode(json_encode($session->get($key)), true));
    }

    public function testToastrIsFlashedAsArray()
    {
        admin_toastr('Saved', 'success');

        $this->assertIsArray(session('toastr'));
    }

    public function testToastrMessageCanNotBreakOutOfTheScript()
    {
        admin_toastr("it's </script><script>alert(1)</script>", 'success; alert(2)');

        $this->visit('admin');

        $this->assertStringNotContainsString('</script><script>alert(1)', $this->response->getContent());
        $this->assertStringNotContainsString('alert(2)', $this->response->getContent());
        $this->assertStringContainsString('toastr.success(', $this->response->getContent());
    }

    public function testToastrSurvivesJsonSerialization()
    {
        admin_toastr('Saved with json', 'warning');

        $this->roundTripThroughJson('toastr');

        $this->visit('admin')
            ->see('toastr.warning("Saved with json"');
    }

    #[DataProvider('messageTypes')]
    public function testMessagesSurviveJsonSerialization($helper, $class)
    {
        $helper('The title', 'The message');

        $this->assertIsArray(session(substr($helper, 6)));

        $this->roundTripThroughJson(substr($helper, 6));

        $this->visit('admin')
            ->seeElement("div.alert.{$class}")
            ->see('The title')
            ->see('The message');
    }

    public static function messageTypes()
    {
        return [
            'success' => ['admin_success', 'alert-success'],
            'error'   => ['admin_error', 'alert-danger'],
            'info'    => ['admin_info', 'alert-info'],
            'warning' => ['admin_warning', 'alert-warning'],
        ];
    }
}
