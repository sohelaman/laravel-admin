<?php

use Illuminate\Support\Facades\File;

class AssetsTest extends TestCase
{
    /**
     * Every asset referenced by the sources and the views exists in resources/assets.
     */
    public function testReferencedAssetsExist()
    {
        $root = realpath(__DIR__.'/..');
        $missing = [];

        $files = array_merge(
            File::allFiles($root.'/src'),
            File::allFiles($root.'/resources/views'),
            [new SplFileInfo($root.'/config/admin.php')]
        );

        foreach ($files as $file) {
            preg_match_all('#vendor/laravel-admin/([A-Za-z0-9_./@-]+\.(?:js|css|png|jpg|gif|json))#', file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $asset) {
                // files written by the admin:minify command
                if (preg_match('#^(laravel-admin\.min\.|minify-manifest)#', $asset)) {
                    continue;
                }

                if (!file_exists($root.'/resources/assets/'.$asset)) {
                    $missing[$asset] = $file->getFilename();
                }
            }
        }

        $this->assertSame([], $missing, 'Assets referenced but not shipped.');
    }

    public function testJQueryIsServed()
    {
        $this->assertFileExists(__DIR__.'/../resources/assets/'.str_replace('vendor/laravel-admin/', '', \Encore\Admin\Admin::$jQuery));
    }

    public function testLoginPageLoadsTheShippedJQuery()
    {
        $this->visit('admin/auth/login')
            ->see('jQuery-3.7.1.min.js');
    }

    public function testShippedLibrariesAreNotTheVulnerableVersions()
    {
        $assets = __DIR__.'/../resources/assets/';

        $this->assertFileDoesNotExist($assets.'AdminLTE/plugins/jQuery/jQuery-2.1.4.min.js');

        $this->assertStringContainsString('v3.4.1', file_get_contents($assets.'AdminLTE/bootstrap/js/bootstrap.min.js'));
        $this->assertStringContainsString('Select2 4.1.0', file_get_contents($assets.'AdminLTE/plugins/select2/select2.full.min.js'));
        $this->assertMatchesRegularExpression('/^\/\/! version : 2\.(29\.[4-9]|[3-9]\d)\./m', substr(file_get_contents($assets.'moment/min/moment-with-locales.min.js'), 0, 200));
    }
}
