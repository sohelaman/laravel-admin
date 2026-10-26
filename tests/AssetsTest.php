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

    /**
     * Nothing may be loaded from another origin (cdn, fonts, images, scripts).
     */
    public function testSourcesDoNotLoadExternalAssets()
    {
        $root = realpath(__DIR__.'/..');
        $external = '(?:https?:)?//(?!localhost)[A-Za-z0-9.-]+\.[a-z]{2,}';
        $found = [];

        $files = array_merge(
            File::allFiles($root.'/src'),
            File::allFiles($root.'/resources/views'),
            File::allFiles($root.'/resources/assets/laravel-admin'),
            File::allFiles($root.'/resources/assets/google-fonts')
        );

        foreach ($files as $file) {
            // The map field is the documented exception, it is disabled by `allow_external_map_api`.
            if ($file->getFilename() === 'Map.php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            $patterns = [
                '#<(?:script|img|iframe|source|video|audio)\b[^>]*\bsrc=["\']?'.$external.'#i',
                '#<link\b[^>]*\bhref=["\']?'.$external.'#i',
                '#\burl\(\s*["\']?'.$external.'#i',
                '#@import\s+(?:url\()?["\']?'.$external.'#i',
                '#\$(?:js|css)\s*=\s*\[?\s*["\']'.$external.'#',
                '#^\s*["\']'.$external.'[^"\']*\.(?:js|css|png|jpe?g|gif|svg|woff2?|ttf)["\'],?$#mi',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $match)) {
                    $found[$file->getFilename()] = trim($match[0]);
                }
            }
        }

        $this->assertSame([], $found, 'External assets are loaded.');
    }

    public function testShippedStylesheetsDoNotImportExternalFiles()
    {
        $found = [];

        foreach (File::allFiles(__DIR__.'/../resources/assets') as $file) {
            if ($file->getExtension() !== 'css') {
                continue;
            }

            if (preg_match('#(?:url\(\s*["\']?|@import\s+(?:url\()?["\']?)(?:https?:)?//[A-Za-z0-9.-]+\.[a-z]{2,}#i', file_get_contents($file->getPathname()), $match)) {
                $found[$file->getPathname()] = $match[0];
            }
        }

        $this->assertSame([], $found);
    }

    public function testPagesOnlyReferenceLocalAssets()
    {
        $this->be(\Encore\Admin\Auth\Database\Administrator::first(), 'admin');

        $external = [];

        foreach (['admin', 'admin/auth/users', 'admin/auth/users/create', 'admin/auth/roles/create', 'admin/auth/menu', 'admin/auth/logs', 'admin/auth/setting', 'admin/images/create', 'admin/files/create', 'admin/users/create', 'admin/users'] as $uri) {
            $this->visit($uri);

            $this->crawler()->filter('script[src], link[href], img[src], source[src], iframe[src]')->each(function ($node) use (&$external, $uri) {
                $url = $node->attr('src') ?? $node->attr('href');

                if ($node->nodeName() === 'a' || !preg_match('#^(https?:)?//#i', (string) $url)) {
                    return;
                }

                if (parse_url($url, PHP_URL_HOST) !== parse_url($this->baseUrl, PHP_URL_HOST)) {
                    $external[$uri][] = $url;
                }
            });
        }

        $this->assertSame([], $external, 'Pages load assets from another origin.');

        // the login page, without being authenticated
        $this->app['auth']->guard('admin')->logout();
        $this->visit('admin/auth/login');

        $this->assertCount(0, $this->crawler()->filter('script[src^="//"], script[src^="http"]:not([src*="localhost"])'));
    }

    public function testMapFieldIsDisabledByDefault()
    {
        $field = new \Encore\Admin\Form\Field\Map('latitude', ['longitude', 'Position']);

        $this->assertSame([], \Encore\Admin\Form\Field\Map::getAssets());
        $this->assertStringNotContainsString('google', (string) (new ReflectionProperty($field, 'script'))->getValue($field));
    }

    public function testMapFieldCanBeEnabled()
    {
        config(['admin.allow_external_map_api' => true]);

        $field = new \Encore\Admin\Form\Field\Map('latitude', ['longitude', 'Position']);

        $this->assertNotEmpty(\Encore\Admin\Form\Field\Map::getAssets()['js']);
        $this->assertStringContainsString('google', (string) (new ReflectionProperty($field, 'script'))->getValue($field));
    }
}
