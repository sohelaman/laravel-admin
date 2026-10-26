# Installation

> This version requires PHP 8.5 and Laravel 13.

First, install laravel, and make sure that the database connection settings are correct.

Then install require this package with command:
```
composer require encore/laravel-admin
```

Publish assets and config with command：
```
php artisan vendor:publish --provider="Encore\Admin\AdminServiceProvider"
```

After runnung previous command you can find config file in `config/admin.php`, in this file you can change default install directory (```/app/Admin```), db connection or table names.

At last run following command to finish install:
```
php artisan admin:install
```

To check that all is working, run `php artisan serve` and open `http://localhost/admin/` in browser, use username `admin` and password `admin` to login. Change this password right after the first login.

The upload disk (`admin` by default) has to be defined in `config/filesystems.php`, for example:

```php
'admin' => [
    'driver'     => 'local',
    'root'       => public_path('uploads'),
    'visibility' => 'public',
    'url'        => env('APP_URL').'/uploads',
    'throw'      => false,
],
```

## Generated files

After the installation is complete, the following files are generated in the project directory:

### Configuration file

After the installation is complete, all configurations are in the `config/admin.php` file.

### Admin files

After install,you can find directory`app/Admin`,and then most of our develop work is under this directory.

```
app/Admin
├── Controllers
│   ├── ExampleController.php
│   └── HomeController.php
├── bootstrap.php
└── routes.php
```

`app/Admin/routes.php` is used to define routes.

`app/Admin/bootstrap.php` is bootstrapper for laravel-admin, for usage examples see comments inside it.

The `app/Admin/Controllers` directory is used to store all the controllers.
The `HomeController.php` file under this directory is used to handle home request of admin.
The `ExampleController.php` file is a controller example.

### Static assets

The front-end static files are in the `/public/packages/admin` directory.
