# Upgrade guide

## Upgrading to 3.0 (Laravel 13, PHP 8.5)

This release supports **Laravel 13 and PHP 8.5 only**. Applications on older versions of Laravel or PHP have to stay on the previous release of the package.

### Requirements

- PHP `^8.5`, Laravel `^13.0`.
- `symfony/dom-crawler` `^7.4|^8.0` and `symfony/css-selector` are now required (they are installed with the package).
- `doctrine/dbal` is no longer required, Laravel's own schema builder is used.
- The testing tools (`orchestra/testbench`, `laravel/browser-kit-testing ^7.2`, `phpunit`) are the ones supported by Laravel 13.

### Image manipulation (`intervention/image`)

Image fields now use `intervention/image` `^4.0` through `intervention/image-laravel` (`composer require intervention/image intervention/image-laravel`). Intervention Image 2.x is not supported any more.

- Methods called on an image field (`$form->image('avatar')->rotate(90)`) are forwarded to the 4.x API. `flip('v')` and `flip('h')` keep working, the other methods have the signature of Intervention Image 4.x (`resize`, `scale`, `cover`, `contain`, `crop`, ...).
- Thumbnails (`->thumbnail(...)`) keep the aspect ratio and fill the remaining space of the canvas with white. The action of a thumbnail (third value of the size) is mapped from the 2.x names: `resize` to `scale` and `fit` to `cover`. Any other value is the name of a 4.x method taking `($width, $height)`.

### Console commands

- `admin:make` and `admin:controller` read the columns with Laravel's schema builder. The generated field types depend on the types reported by the database, so the generated code may differ from the previous release for some columns (for example `tinyint` is mapped to a switch).
- `admin:permissions` works with every database driver (it was MySQL only).
- `admin:install` reminds you to change the default password of the administrator.

### Behaviour changes

- `vendor:publish --tag=laravel-admin-lang` publishes the translations to `lang_path()`.
- The operation log masks the values of `password`, `password_confirmation`, `current_password` and `_token`, nested values included. Add your own fields with the `operation_log.redact` option of `config/admin.php`. Existing log entries are not modified.
- Failed logins are throttled: after 5 failed attempts the user is locked out for a minute (`$maxLoginAttempts` and `$lockoutSeconds` of the `AuthController`).
- The `password` column of the administrator table is created as `string(255)`. Existing installations can widen it with a migration of their own if they use hashers longer than bcrypt.
- Methods with an optional typed parameter declare it nullable (`?Closure $callback = null`), as required since PHP 8.4. Classes overriding them keep working.

### Laravel 13

- The CSRF middleware of Laravel 13 (`PreventRequestForgery`) verifies the origin of the requests, make sure the admin and the pages sending requests to it share the same origin.
- The session `serialization` option defaults to `json`. The messages flashed by `admin_toastr()`, `admin_success()`, `admin_error()`, `admin_info()`, `admin_warning()` and the exception handler are now stored in the session as arrays (they were `MessageBag` objects, which are not restored from a json session and made every page fail after a flash). Code reading these values from the session has to build the bag with `admin_message_bag($value)`.
- Validation messages come from the framework, some of them changed in recent versions of Laravel (`The email field must be a valid email address.`).

### Front-end

The bundled libraries were upgraded (jQuery 3.7.1, Bootstrap 3.4.1, AdminLTE 2.4.18, select2 4.1, sweetalert2 11, bootstrap-fileinput 5, inputmask 5, moment 2.31, ...), see `resources/assets/VERSIONS.md` and `docs/frontend-dependency-audit.md`. Publish the assets again after the update: `php artisan admin:publish --force`.

- `Admin::$jQuery` points to `jQuery-3.7.1.min.js`, `AdminLTE/dist/js/app.min.js` is `adminlte.min.js`, `input-mask/jquery.inputmask.bundle.min.js` is `jquery.inputmask.min.js`, `sweetalert2/dist/sweetalert2.css` is `sweetalert2.min.css`. Extensions that reference these files have to follow.
- Custom views that copy the header or the sidebar must use `data-toggle="push-menu"` for the toggle button and `data-widget="tree"` on the menu (AdminLTE 2.4).
- `swal(...)` still works (it calls `Swal.fire`, `type` is mapped to `icon`) but `Swal` is the sweetalert2 11 object; the result of a dialog is `{isConfirmed, isDenied, isDismissed, value}`.
- The slider field options `prettify` and `hasGrid` are `prettify_enabled` and `grid` in ion.rangeSlider 2 (the old names are still translated by the field).
- Grid displayers escape the value they display (`label`, `badge`, `link`, `button`, `prefix`, `suffix`, `limit`, `secret`, `copyable`, `qrcode`, `downloadable`, `image`). A `display()` callback that returns HTML for a following displayer has to return an `Illuminate\Support\HtmlString`. `link()` refuses `javascript:` urls.
- The message of `admin_toastr()` is written as a JSON string in the page, HTML in it still works.
- jQuery 3 removed `.load()`, `.error()`, `.success()` and `.size()`; extensions using them have to change.

### Security hardening

- The grid sort (`_sort[column]`, `_sort[type]`, `_sort[cast]`) is validated: the column has to be a column name (or a path in a json column), the direction `asc` or `desc`, the cast one of `signed`, `unsigned`, `char`, `binary`, `date`, `datetime`, `time`, `json`, `decimal(m,n)`. Other values are ignored; they were written in the query.
- The classes requested by the `_handle_form_`, `_handle_action_`, `_handle_selectable_` and `_handle_renderable_` routes must extend `Encore\Admin\Widgets\Form`, `Encore\Admin\Actions\Action`, `Encore\Admin\Grid\Selectable` and implement `Illuminate\Contracts\Support\Renderable`. Unknown classes answer 404 (the selectable and renderable routes used to print the requested name).
- Uploads: files with an extension of `upload.forbidden_extensions` in `config/admin.php` (scripts and executables by default, any part of the file name is checked: `shell.php.jpg`) are refused with a validation error. Add the key to a published `config/admin.php`, an application without it uses the default list. Set it to an empty array to disable the check.
