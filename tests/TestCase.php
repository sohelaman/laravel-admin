<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Laravel\BrowserKitTesting\TestCase as BaseTestCase;
use Orchestra\Testbench\Foundation\Application as TestbenchApplication;

class TestCase extends BaseTestCase
{
    protected $baseUrl = 'http://localhost:8000';

    /**
     * Directory the package writes its published and generated files to,
     * shared by every test of the process.
     *
     * @var string
     */
    protected static $workingPath;

    /**
     * Static state of the Admin class before the first test.
     *
     * @var array|null
     */
    protected static $adminDefaults;

    /**
     * Boots the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = TestbenchApplication::create(options: [
            'load_environment_variables' => false,
            'extra'                      => ['dont-discover' => ['*']],
        ]);

        // Let PHPUnit report deprecations and warnings instead of Laravel's error handler.
        restore_error_handler();
        restore_exception_handler();

        // CSRF protection is skipped for unit tests, as in a regular Laravel application.
        $app->detectEnvironment(function () {
            return 'testing';
        });

        $path = static::workingPath();

        $app->useAppPath($path.'/app');
        $app->useConfigPath($path.'/config');
        $app->useDatabasePath($path.'/database');
        $app->useLangPath($path.'/lang');
        $app->usePublicPath($path.'/public');

        // Translations are published to the language path above, so the translator has to be rebuilt.
        $app->forgetInstance('translation.loader');
        $app->forgetInstance('translator');
        Facade::clearResolvedInstance('translator');

        $this->registerAdminNamespace($path.'/app/Admin');

        $this->configure($app);

        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('Admin', \Encore\Admin\Facades\Admin::class);

        $app->register('Encore\Admin\AdminServiceProvider');
        $app->register(\Intervention\Image\Laravel\ServiceProvider::class);

        return $app;
    }

    /**
     * Get (and create) the directory used for generated files.
     *
     * @return string
     */
    protected static function workingPath()
    {
        if (static::$workingPath === null) {
            $path = sys_get_temp_dir().'/laravel-admin-tests-'.bin2hex(random_bytes(8));

            mkdir($path, 0700, true);

            register_shutdown_function(function () use ($path) {
                (new Filesystem())->deleteDirectory($path);
            });

            static::$workingPath = $path;
        }

        return static::$workingPath;
    }

    /**
     * Make the generated `App\Admin` classes loadable.
     *
     * @param string $directory
     */
    protected function registerAdminNamespace($directory)
    {
        static $registered = false;

        if (!class_exists('App\Http\Controllers\Controller')) {
            class_alias(\Illuminate\Routing\Controller::class, 'App\Http\Controllers\Controller');
        }

        if ($registered) {
            return;
        }

        spl_autoload_register(function ($class) use ($directory) {
            if (strpos($class, 'App\\Admin\\') !== 0) {
                return;
            }

            $file = $directory.'/'.str_replace('\\', '/', substr($class, strlen('App\\Admin\\'))).'.php';

            if (is_file($file)) {
                require_once $file;
            }
        });

        $registered = true;
    }

    /**
     * Set the configuration used by the tests.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function configure($app)
    {
        $adminConfig = require __DIR__.'/../config/admin.php';

        $config = $app['config'];

        $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $config->set('database.default', env('DB_CONNECTION', 'sqlite'));
        $config->set('database.connections.sqlite.database', ':memory:');
        $config->set('database.connections.mysql.host', env('MYSQL_HOST', 'localhost'));
        $config->set('database.connections.mysql.port', env('MYSQL_PORT', '3306'));
        $config->set('database.connections.mysql.database', env('MYSQL_DATABASE', 'laravel_admin_test'));
        $config->set('database.connections.mysql.username', env('MYSQL_USER', 'root'));
        $config->set('database.connections.mysql.password', env('MYSQL_PASSWORD', ''));
        $config->set('database.connections.pgsql.host', env('PGSQL_HOST', 'localhost'));
        $config->set('database.connections.pgsql.port', env('PGSQL_PORT', '5432'));
        $config->set('database.connections.pgsql.database', env('PGSQL_DATABASE', 'laravel_admin_test'));
        $config->set('database.connections.pgsql.username', env('PGSQL_USER', 'postgres'));
        $config->set('database.connections.pgsql.password', env('PGSQL_PASSWORD', ''));
        $config->set('filesystems', require __DIR__.'/config/filesystems.php');
        $config->set('admin', $adminConfig);
        $config->set('admin.grid_action_class', \Encore\Admin\Grid\Displayers\Actions::class);

        foreach (Arr::dot(Arr::get($adminConfig, 'auth'), 'auth.') as $key => $value) {
            $config->set($key, $value);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetAdminState();

        // The factories give the same data on every run.
        fake()->seed(20261026);

        $this->artisan('vendor:publish', ['--provider' => 'Encore\Admin\AdminServiceProvider']);

        // The translations were not published yet when the translator was used for the first time.
        $this->app['translator']->setLoaded([]);

        Schema::defaultStringLength(191);

        $this->artisan('admin:install');

        (new Filesystem())->ensureDirectoryExists(public_path('uploads'));

        $this->migrateTestTables();

        if (file_exists($routes = admin_path('routes.php'))) {
            require $routes;
        }

        require __DIR__.'/routes.php';
    }

    /**
     * Scripts, styles and callbacks registered by a test must not leak into the next one.
     */
    protected function resetAdminState()
    {
        $admin = new ReflectionClass(\Encore\Admin\Admin::class);

        if (static::$adminDefaults === null) {
            static::$adminDefaults = $admin->getStaticProperties();
        }

        foreach (static::$adminDefaults as $name => $value) {
            $admin->setStaticPropertyValue($name, $value);
        }
    }

    protected function tearDown(): void
    {
        $this->resetAdminState();

        // Leave the database empty for the next test (matters for non in-memory connections),
        // but never touch a database that is not meant for the tests.
        $connection = $this->app['db']->connection();

        if ($connection->getDatabaseName() === ':memory:' || stripos($connection->getDatabaseName(), 'test') !== false) {
            $connection->getSchemaBuilder()->dropAllTables();
        }

        parent::tearDown();
    }

    /**
     * run package database migrations.
     *
     * @return void
     */
    public function migrateTestTables()
    {
        $fileSystem = new Filesystem();

        $fileSystem->requireOnce(__DIR__.'/migrations/2016_11_22_093148_create_test_tables.php');

        (new CreateTestTables())->up();
    }
}
