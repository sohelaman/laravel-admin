# Migrating a project from `encore/laravel-admin` 1.8.19 to this fork

For: developers of an application built on the original package at **1.8.19**.
This fork starts at the 1.8.19 tag (no upstream code changed after it), so everything below is the difference between 1.8.19 and this version.

What changes at a glance:

| | 1.8.19 (original) | This fork |
| --- | --- | --- |
| PHP / Laravel | `>=7.0` / `>=5.5` (tested up to Laravel 9, PHP 8.2) | **`^8.5` / `^13.0` only** |
| Image manipulation | `intervention/image` 2.x | `intervention/image` **4.x** + `intervention/image-laravel` |
| `doctrine/dbal` | required | not needed |
| jQuery, Bootstrap, AdminLTE | 2.1.4, 3.3.x, 2.3.2 | 3.7.1, 3.4.1, 2.4.18 (plus ~20 plugins, see `resources/assets/VERSIONS.md`) |
| Package name | `encore/laravel-admin` | still `encore/laravel-admin` (rename undecided) |

The details of every behaviour change are in `UPGRADE.md`; this guide tells you what to do and what to look for in your own code.

---

## Part 1. Replacing the package

### Step 0. Prepare

1. Work on a branch and make sure you have a database backup. The package has **no new database migration** (see step 8), but the application upgrade of step 2 may have its own.
2. Run the test suite (or click through the admin) on the current version first, so you know what "working" looks like.
3. Note your customisations: published `config/admin.php`, published translations (`lang/*/admin.php`), copies of package views under `resources/views/vendor/admin`, the `app/Admin` directory (routes, bootstrap, controllers, custom fields/actions/displayers), and the extensions you installed (`laravel-admin-ext/*`).

### Step 1. Make sure the extensions can follow

Every `laravel-admin-ext/*` package (helpers, backup, media-manager, config, log-viewer, api-tester, scheduling, redis-manager, wangEditor, summernote, ...) was written for Laravel 5 to 9 and is unmaintained. Each one must work on Laravel 13 / PHP 8.5 and on this package. For each extension:

- check that it installs under Laravel 13 (`composer why-not laravel/framework 13`), and read its code for the changes of Part 2 (Intervention, jQuery 3, `swal`, Doctrine, `array_*`/`str_*` helpers, implicit nullable parameters);
- if it does not, replace it, fork it, or drop it before you go on. This is the most likely blocker of the whole migration.

### Step 2. Upgrade the application to Laravel 13 and PHP 8.5

The package only supports Laravel 13, so the application has to be on Laravel 13 first (or in the same step). If your application is older than Laravel 13, follow the official upgrade guides one major at a time (the 12.x to 13.x guide is at https://laravel.com/docs/13.x/upgrade, Laravel Shift can automate most of it), and move the runtime to PHP 8.5.

Points of the Laravel 13 / 11+ upgrade that touch an admin panel:

- `session.serialization` defaults to `json`: objects stored in the session (or flashed) do not survive (see "Flash messages" in Part 2).
- The CSRF middleware became `PreventRequestForgery` and checks the origin of requests; if the admin is served from a sub-domain (`admin.route.domain`) test login and form posts.
- The default `local` filesystem disk root changed in Laravel 12 (`storage/app/private`). The admin upload disk is the one you configured in `config/filesystems.php` (`admin` by default), so it is not affected, but check that it is still defined and that its `root` and `url` are what you expect.
- The slim application skeleton: the package registers its own middleware and routes, no change in `bootstrap/app.php` is needed for the admin.

### Step 3. Point Composer at the fork

The fork is not on Packagist. Use a VCS repository (after the branch is pushed) or a path repository (local checkout):

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github.com:sohelaman/laravel-admin.git" }
    ],
    "require": {
        "php": "^8.5",
        "laravel/framework": "^13.0",
        "encore/laravel-admin": "dev-revive as 1.8.99"
    }
}
```

- `dev-revive` is the branch the fork lives on today; use the tag instead once a release tag exists (for example `^3.0`).
- Keep the alias (`as 1.8.99`) as long as you use official extensions: they require `encore/laravel-admin` as `~1.x`, which a plain `dev-` branch or a `3.x` tag does not satisfy.
- For a local checkout use `{ "type": "path", "url": "../laravel-admin", "options": { "symlink": true } }` instead of the VCS entry.
- Composer prefers the repositories you list over Packagist for the same package name, so the original is not installed.

### Step 4. Update the other dependencies

```bash
# the package does not use Doctrine DBAL any more; remove it if nothing else in your project needs it
composer remove doctrine/dbal

# Intervention Image 2.x is not supported: move to 4.x (only if you use image fields with manipulation or thumbnails)
composer remove intervention/image
composer require intervention/image:^4.0 intervention/image-laravel:^4.0

# only if you use the Excel grid exporter
composer require maatwebsite/excel   # a release that supports Laravel 13

composer update
```

If you use images, publish the Intervention configuration and pick the driver (`gd` or `imagick`) you have installed:

```bash
php artisan vendor:publish --provider="Intervention\Image\Laravel\ServiceProvider"
```

Other things Composer may complain about: `symfony/dom-crawler` (the package now requires `^7.4|^8.0` and `symfony/css-selector`; remove an old pin), `fzaninotto/faker` (replaced by `fakerphp/faker` in dev), old test tooling.

### Step 5. Re-publish the assets

The bundled JavaScript and CSS libraries were all replaced, several files were renamed or removed. Remove the old copy first so no stale file stays in the web root, then publish **only the assets**:

```bash
rm -rf public/vendor/laravel-admin
php artisan vendor:publish --tag=laravel-admin-assets --force
php artisan view:clear
```

Do **not** run `php artisan admin:publish --force`: it also overwrites `config/admin.php`, the translations and the migrations with the package defaults.

Browsers and CDNs may keep the old files: bump your asset version / purge the cache of `/vendor/laravel-admin/*`.

### Step 6. Merge the configuration

`config/admin.php` keeps the same structure. Two keys were added, both optional (the code has defaults):

```php
// config/admin.php

'upload' => [
    'disk' => 'admin',
    'directory' => ['image' => 'images', 'file' => 'files'],

    // Files with one of these extensions (any part of the name, `shell.php.jpg` included) are refused.
    'forbidden_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'inc',
        'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp', 'jspx', 'exe', 'bat', 'cmd', 'htaccess',
    ],
],

'operation_log' => [
    // ...
    // Input fields masked in the log, in addition to password, password_confirmation, current_password, _token.
    'redact' => [],
],
```

`php artisan admin:config` shows the difference between your file and the package's. If your project uploads one of the listed extensions on purpose (for example `.py` or `.sh` files for a download area), remove it from the list; set the list to `[]` only if you serve uploads from a place that never executes scripts.

If you published the translations, add the new key to your `admin.php` language files (English fallback is used otherwise):

```php
'upload_forbidden_extension' => 'This type of file can not be uploaded.',
```

### Step 7. Copies of package views

If you copied any package view into `resources/views/vendor/admin/` they override the new ones. Compare them with the package (`git diff v1.8.19 -- resources/views` in the fork); the seven changed views are:

| View | Change |
| --- | --- |
| `partials/header.blade.php` | sidebar toggle is `data-toggle="push-menu"` (was `offcanvas`), required by AdminLTE 2.4 |
| `partials/sidebar.blade.php` | `<ul class="sidebar-menu" data-widget="tree">` |
| `partials/alerts.blade.php`, `partials/toastr.blade.php` | read the flashed messages through `admin_message_bag()`, toastr message is JSON encoded |
| `login.blade.php` | jQuery file is `jQuery-3.7.1.min.js` |
| `widgets/carousel.blade.php` | id printed with `{{ }}` |
| `filter/button.blade.php` | `.off('click')` instead of `.unbind('click')` |

The simplest path is to delete your copies, then re-apply only the customisation you really need on top of the new files.

### Step 8. Database

There is **no new migration**. Two optional points:

- New installations create `admin_users.password` as `string(255)` (was 60, enough for bcrypt only). If you use another hasher (argon2) or want to be safe, widen the column in a migration of your own:

  ```php
  Schema::table(config('admin.database.users_table'), function (Blueprint $table) {
      $table->string('password', 255)->change();
  });
  ```
- The operation log used to store request bodies **unredacted**, passwords included. Rows written before the upgrade may still contain them; review `admin_operation_log.input` and scrub it (this matters for payment/PCI environments and for data-protection obligations such as those of Bangladesh Bank): for example `UPDATE admin_operation_log SET input = '[REDACTED]' WHERE input LIKE '%password%'`. Also check that the built-in `admin` / `admin` account was changed or removed.

### Step 9. Clear caches and smoke test

```bash
php artisan optimize:clear
php artisan route:list --path=admin | head
```

Then walk through the checklist at the end of Part 2.

---

## Part 2. Changes in your own code

Find what concerns you with the `grep` commands given for each point (run them in `app/`, `app/Admin/` and `resources/views/`).

### A. Changes everyone has to make or check

#### A1. PHP 8.4/8.5 signatures (only if you subclass the package)

Package methods now declare optional typed parameters as nullable (`?Closure $callback = null`). Classes of yours that override such methods keep working, but PHP 8.4 raises a deprecation for `Closure $x = null` in **your** code. Fix those signatures:

```php
// before
public function setForm(Form $form = null) { ... }
// after
public function setForm(?Form $form = null) { ... }
```

Check custom fields (`Encore\Admin\Form\Field` subclasses), displayers, tools, actions, exporters:
`grep -rnE "(Closure|Form|Grid|Model|Field|UploadedFile|array) \$\w+ = null" app/`.

PHP 8.2+ also reports undeclared dynamic properties on your classes; declare them.

#### A2. Flash messages and the session

Laravel 13 serializes the session as JSON. The package now flashes `admin_toastr()`, `admin_success()`, `admin_error()`, `admin_info()`, `admin_warning()` and the exception handler messages **as arrays**. If you wrote the flash yourself or read it:

```php
// before: an object, lost when the session is JSON
session()->flash('toastr', new \Illuminate\Support\MessageBag(['type' => ['success'], 'message' => ['Saved']]));
$bag = session('toastr');

// after: use the helpers, or flash an array and rebuild the bag when reading
admin_toastr('Saved', 'success');
$bag = admin_message_bag(session('toastr'));
```

`grep -rnE "session\(\)->flash\('(toastr|success|error|info|warning)'|session\('(toastr|success|error|info|warning)'\)|MessageBag" app/ resources/views/`.
Do not "fix" it by setting `session.serialization` back to `php`; that disables a security hardening of Laravel 13.

#### A3. Image fields (Intervention Image 4)

Anything you call on an image field is forwarded to Intervention Image, whose API changed completely between 2.x and 4.x. `flip('v')` / `flip('h')` are still accepted, the rest has the 4.x signature.

```php
// before (2.x)
$form->image('photo')->resize(300, 200, function ($constraint) { $constraint->aspectRatio(); });
$form->image('photo')->fit(300, 200);
$form->image('photo')->thumbnail('small', 100, 100);
$form->image('photo')->thumbnail(['small' => [100, 100], 'cover' => [60, 60, 'fit']]);

// after (4.x)
$form->image('photo')->scale(300, 200);          // keeps the ratio, the old resize + aspectRatio
$form->image('photo')->cover(300, 200);          // the old fit()
$form->image('photo')->thumbnail('small', 100, 100);                        // unchanged
$form->image('photo')->thumbnail(['small' => [100, 100], 'cover' => [60, 60, 'fit']]);  // 'fit' is mapped to cover(), 'resize' to scale()
```

Rules of thumb: `widen()`/`heighten()` become `scale(width: ...)` / `scale(height: ...)`; `crop()` takes `(width, height, offset_x, offset_y, background, position)` (no longer centered by default, use `cover()` for that); `encode('jpg', 80)` is `encodeUsingFileExtension()` / `toJpeg(80)`; `text()` has a new font API; `rotate()` should be checked on a sample image for its direction. See https://image.intervention.io/v4 for the rest.
Thumbnails keep the aspect ratio and fill the rest of the canvas with white, as before.

In your own code replace the facade and its methods:

```php
// before
use Intervention\Image\Facades\Image;
$image = Image::make($path);
// after
use Intervention\Image\Laravel\Facades\Image;
$image = Image::decodePath($path);   // or Image::decode($source)
```

`grep -rn "Intervention" app/ resources/views/` and `grep -rnE "(image|multipleImage)\(" app/Admin/ -A2`.

#### A4. Custom JavaScript (sweetalert2 11, jQuery 3, AdminLTE 2.4, Bootstrap 3.4.1)

Applies to code in `Admin::script(...)`, `Admin::js(...)`, custom fields/actions, `resources/views`:

- **`swal(...)`** still works (it is a wrapper that calls `Swal.fire` and maps the old `type` option to `icon`), but it is a function, not the sweetalert2 object. Replace calls of the static helpers: `swal.close()` → `Swal.close()`, `swal.showLoading()` → `Swal.showLoading()`, `swal.enableLoading()` → `Swal.showLoading()`, `swal.showValidationError()` → `Swal.showValidationMessage()`, `swal.getContent()` → `Swal.getHtmlContainer()`. Options removed since 7.x: `confirmButtonClass`/`cancelButtonClass` (use `customClass`), `onOpen`/`onClose` (`didOpen`/`willClose`), `useRejections`. The resolved value is an object: use `result.isConfirmed`, `result.isDismissed`, `result.value`. `grep -rn "swal" app/ resources/views/`.
- **jQuery 3.7** removed `.load(fn)`, `.error(fn)`, `.unload(fn)` event shorthands and the `.success()/.error()/.complete()` callbacks of jqXHR (use `.done()/.fail()/.always()`), `.size()`, `.andSelf()`, `.toggle(fn, fn)`, `.live()`/`.die()`; deprecated (still working) are `$.isArray`, `$.trim`, `$.parseJSON`, `.bind/.unbind`, `$.isFunction`. `:visible` now also matches elements that have no box but are displayed. `grep -rnE "\.(load|error|unload|size|andSelf|live|die)\(|\.success\(|\.complete\(|\.toggle\(function" app/ resources/views/`.
- **AdminLTE 2.4**: the sidebar toggle is `data-toggle="push-menu"`, the menu `ul` needs `data-widget="tree"`, the script is `dist/js/adminlte.min.js` (was `app.min.js`). Custom layouts, navbars (`Admin::navbar`) and widgets that copy the old markup must follow. `grep -rn "offcanvas\|app.min.js" app/ resources/views/`.
- **Bootstrap 3.4.1**: tooltips and popovers with `html: true` sanitize their content (attributes such as `style` are stripped unless you pass a `whiteList`). Do not put untrusted data into `data-*` attributes read by Bootstrap components.
- **Plugins**: if you initialise plugins yourself, check the option names of the new releases: ion.rangeSlider 2 (`prettify` → `prettify_enabled`, `hasGrid` → `grid`; the slider field translates the old names for you), inputmask 5, select2 4.1, bootstrap-fileinput 5 (loaded with the `gly` theme, `canvas-to-blob` no longer exists), fontawesome-iconpicker 3 (the package supplies the Font Awesome 4.7 icon list), nestable2, moment 2.31.

If you register your own copies of assets, these paths changed:

| Old | New |
| --- | --- |
| `AdminLTE/plugins/jQuery/jQuery-2.1.4.min.js` | `AdminLTE/plugins/jQuery/jQuery-3.7.1.min.js` (`Admin::$jQuery`) |
| `AdminLTE/dist/js/app.min.js` | `AdminLTE/dist/js/adminlte.min.js` |
| `AdminLTE/plugins/input-mask/jquery.inputmask.bundle.min.js` | `AdminLTE/plugins/input-mask/jquery.inputmask.min.js` |
| `sweetalert2/dist/sweetalert2.css` | `sweetalert2/dist/sweetalert2.min.css` |
| `AdminLTE/plugins/ionslider/ion.rangeSlider.css`, `...skinNice.css` | `AdminLTE/plugins/ionslider/ion.rangeSlider.min.css` |
| `bootstrap-fileinput/js/plugins/canvas-to-blob.min.js` | removed (add `piexif.min.js`, `purify.min.js`, theme `themes/gly/theme.min.js`) |
| `AdminLTE/plugins/bootstrap-slider/*`, `input-mask/phone-codes/*` | removed (unused) |

#### A5. Console commands

- `admin:make`, `admin:controller` read the columns with Laravel's schema builder instead of Doctrine. The generated code can differ for some column types (for example `tinyint` becomes a switch, `timestamp` a datetime). Re-generate and compare if you rely on the output.
- `admin:permissions` now works with every database.
- `admin:install` prints a reminder to change the default password.

### B. Features and behaviour changes to check

| Area | What changed | Check in your code |
| --- | --- | --- |
| **Grid displayers** | `label`, `badge`, `link`, `button`, `prefix`, `suffix`, `limit`, `secret`, `copyable`, `qrcode`, `downloadable`, `image` now **escape** the value (they printed it raw: stored XSS). `link()` refuses `javascript:`, `vbscript:`, `data:` urls. | A `display()` callback followed by one of these displayers that returns HTML must return `new \Illuminate\Support\HtmlString($html)`. A callback used **alone** is unchanged and still returns raw HTML. `grep -rnE "display\(function" app/Admin -A4`, then look at the chained call |
| **Grid sort** | `_sort[column]` must be a column name or a path in a json column made of letters, digits and `_` (`data.json.field`); `_sort[type]` `asc`/`desc`; `_sort[cast]` one of `signed`, `unsigned`, `char`, `binary`, `date`, `datetime`, `time`, `json`, `decimal(m,n)`. Anything else is ignored (it used to be written into the SQL: SQL injection). | JSON keys with `-`, spaces or brackets cannot be used for sorting any more; custom sort links with other casts |
| **Action / form / selectable / renderable routes** | The classes requested through `_handle_form_`, `_handle_action_`, `_handle_selectable_`, `_handle_renderable_` must extend `Encore\Admin\Widgets\Form`, `Encore\Admin\Actions\Action`, `Encore\Admin\Grid\Selectable`, or implement `Illuminate\Contracts\Support\Renderable`. Others answer 404 / an error. | Custom widgets/actions that do not extend those base classes |
| **Uploads** | Files with an extension of `upload.forbidden_extensions` are refused with a validation error (checked for every part of the file name and the detected type). | Forms that accept script-like files on purpose; tests that upload `.php` fixtures |
| **Login** | 5 failed attempts per user name and IP lock the login for 60 seconds. Properties `$maxLoginAttempts`, `$lockoutSeconds` of `AuthController`. | A custom `AuthController` that overrides `postLogin()` has to copy the throttling; a reverse proxy must be a trusted proxy or every user shares the IP |
| **Operation log** | The values of `password`, `password_confirmation`, `current_password`, `_token` (nested too) and of `operation_log.redact` are stored as `******`. | Add the sensitive fields of your forms to `operation_log.redact` (tokens, card/account numbers, PINs) |
| **Slider field** | Options are the ion.rangeSlider 2 ones, skin `round` | `->options([...])` calls: names `prettify`/`hasGrid` are translated, other 1.x names are not |
| **Masks** | inputmask 5: a currency field shows no `0.00` until it gets the focus; aliases (`ip`, `email`, `url`, `integer`, `decimal`, `currency`, `mac`, `percentage`) unchanged; option names follow inputmask 5 (`prefix`, `radixPoint`, `digits`, `groupSeparator`, `removeMaskOnSubmit`) | `->inputmask([...])`, `->currency()`, `->decimal()` with custom options |
| **Colour field** | Starts empty instead of black | |
| **Icon field** | Stores the Font Awesome 4 class as before (`fa-pencil`) | |
| **Toastr messages** | Written as a JSON string, HTML in the message still works | Messages containing quotes are now safe |
| **Dashboard** | Does not fail when `composer.json` has no `require` | |
| **Models** | `Administrator`, `Role`, `Permission`, `Menu`, `OperationLog` and the traits (`ModelTree`, `AdminBuilder`, `DefaultDatetimeFormat`, `HasPermissions`) have the same API | Your own models extending them: check Laravel 13 model changes (`$dates` removed, `casts()` method, nested model instantiation while booting) |
| **Language files** | One new key, `upload_forbidden_extension` | Published translations |

What does **not** change: the routes of the admin, the `app/Admin` directory layout (`routes.php`, `bootstrap.php`, `Controllers`), `AdminController`, the `Grid`/`Form`/`Show`/`Tree` builder API and the 60 field types (apart from the points above), the `Admin::` facade, `admin_url()`/`admin_path()`/`admin_asset()` helpers, the database tables and their configuration, permissions and menu management, `Form::extend()` / `Column::extend()` / `Admin::extend()` for custom components, filters, actions and batch actions.

### C. Your controllers and forms, step by step

1. Controllers extending `Encore\Admin\Controllers\AdminController` (and `HasResourceActions`) need no change for the package.
2. Controllers generated by `admin:make` import `App\Http\Controllers\Controller` only in the blank/stub variants. On the Laravel 11+ skeleton that base class no longer extends the framework controller: do not rely on `$this->middleware()` or `ValidatesRequests` in it (framework change, not package change).
3. In grids: review chained displayers (table above), custom `sortable()` columns on JSON data, custom `display()` returning HTML into `label()`/`badge()`.
4. In forms: review `->image()`/`->multipleImage()` manipulation calls (A3), `->thumbnail()` actions, slider options, upload fields that accept script-like extensions, `->help()`/`->display()` content (raw HTML as before).
5. In custom form fields, actions and displayers: PHP 8.4 nullable signatures (A1), JS (A4), Intervention (A3).
6. In `app/Admin/bootstrap.php` and service providers that register assets with `Admin::js()/css()/headerJs()`: use the new asset paths (A4).
7. Tests of your application that used `Laravel\BrowserKitTesting` with the Laravel 5-9 skeleton: use `laravel/browser-kit-testing ^7.2` and, if you test uploads, do not upload `.php` fixtures.

### D. Verification checklist

After `composer update` and the asset publication, check in a browser and with your test suite:

- [ ] login (also a wrong password, and the lock after 5 failures), logout, "remember me"
- [ ] dashboard, sidebar menu (open/close groups, the active item stays open, collapse button), PJAX navigation between pages
- [ ] every grid of your application: sort (including JSON columns), filters (date pickers, select2), quick search, per-page, export, inline editing, row and batch actions (the confirmation dialogs), delete
- [ ] every form: each field type you use (select2, date/time pickers, switch, color, slider, icon, tags, listbox, file and image upload with preview and delete, thumbnails, `HasMany`/nested forms, validation messages)
- [ ] roles, permissions, menu management (drag and drop), operation log (and that passwords are masked), user setting page (avatar, password)
- [ ] flash messages after saving/deleting (toastr and the alert boxes) with `session.serialization = json`
- [ ] your extensions
- [ ] browser console: no JavaScript errors on any of these pages

### E. Rollback

Revert `composer.json`/`composer.lock` to the 1.8.19 state, `composer install`, restore `public/vendor/laravel-admin` (`rm -rf` it and publish the assets of the old version) and your `config/admin.php`. The database was not changed by the package (apart from what you did in step 8), so no data rollback is needed beyond your own migrations of the Laravel upgrade.

### F. Known limitations of this version

- Bootstrap 3 is end of life; `npm audit` reports one moderate advisory on it that cannot be fixed without moving to Bootstrap 5 (see `docs/frontend-dependency-audit.md`).
- The footer still shows the constant `Admin::VERSION` (`1.8.17`); it is only a label.
- MySQL 8.4 and SQLite are tested; PostgreSQL has not been run; the CI workflow has not been executed (`OUTCOME.md`).
- Extensions are not part of this package and are not tested with it.
