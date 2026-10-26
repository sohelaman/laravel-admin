# Laravel 13 / PHP 8.5 Compatibility Analysis

Package: `encore/laravel-admin` (fork: `sohelaman/laravel-admin`)
Analysis date: 2026-10-26
Baseline: last upstream commit 2022-05-24, claims Laravel `>=5.5`, tested up to Laravel 9 / PHP 8.2.
Targets: **Laravel 13.x only** (13.38.0 at time of writing; the framework itself requires PHP ^8.3) and **PHP 8.5 only**. Decided by the maintainer: no support for other Laravel or PHP versions.

Companion document: [laravel13-php85-migration-plan.md](laravel13-php85-migration-plan.md)

---

## 1. Executive summary

The earlier assumption was that the package would not work on newer Laravel/PHP. **That is only partly true, and the split matters:**

| Layer | Status on Laravel 13.38 + PHP 8.5.11 |
|---|---|
| `composer require` | **Blocked** by three constraints in `composer.json` (see 3.1). |
| Runtime, once Composer is unblocked | **Mostly works.** Every admin page rendered (HTTP 200); user/menu/permission/setting create, update and delete worked. |
| Artisan generators | **`admin:make` and `admin:controller` are broken** (Doctrine DBAL removed from Laravel). `admin:permissions` is MySQL-only. |
| PHP 8.4 / 8.5 deprecations | **35 deprecation notices in 24 files**, plus 2 runtime ones. Not fatal yet, but noisy and they will become errors in PHP 9. |
| Test suite | **Cannot run as-is.** It depends on the `laravel/laravel` skeleton, legacy model factories and a PHPUnit 7/8-era `phpunit.xml.dist`. |
| Front-end assets | Independent of Laravel, but very old (AdminLTE 2 / Bootstrap 3 / jQuery). Out of scope for compatibility, in scope for security hygiene. |

**Bottom line:** this is a *modernisation* job, not a rewrite. The expensive parts are the test harness and the generator command. The framework-facing runtime code (routing, auth, middleware, Eloquent usage, Blade views, Flysystem usage) needed no changes to render and save on Laravel 13.

> **Caveat on confidence.** The empirical result below comes from a smoke test, not from the package's own test suite (which cannot run). It covers the core admin pages and CRUD. It does **not** cover image upload with thumbnails, the CSV/Excel export paths beyond a manual check, tree/orderable grids, `HasMany`/nested forms, most of the 60+ form field types, or MySQL/PostgreSQL-specific behaviour (the smoke test used SQLite).

---

## 2. Method and evidence

### 2.1 What was done

1. Static review of the whole `src/` tree (301 PHP files), `composer.json`, config, migrations, console stubs, Blade views, and tests.
2. `php -l` over every PHP file with `error_reporting=-1` on **PHP 8.5.11** to collect compile-time deprecations.
3. `composer update --dry-run` against Laravel 13 to see which dev tooling resolves.
4. Read of the official Laravel 13.x upgrade guide (12.x to 13.x) to map each documented change against package code.
5. **Throwaway experiment** (in the session scratchpad; no project files changed):
   - Fresh `laravel/laravel ^13.0` skeleton.
   - A *copy* of this package with only `composer.json` patched (`symfony/dom-crawler ^7.4|^8.0`, `symfony/css-selector` added, `doctrine/dbal` removed, `laravel/framework ^13.0`).
   - SQLite, `php artisan vendor:publish`, `admin:install`, then requests through the HTTP kernel as a logged-in admin, with a custom error handler capturing deprecations.

### 2.2 Experiment results

Resolved stack: laravel/framework 13.38.0, symfony/* 8.1.x, nesbot/carbon 3.15, PHPUnit 13.4, PHP 8.5.11.

| Check | Result |
|---|---|
| `php artisan list` | All 18 `admin:*` commands registered |
| `vendor:publish` (config, lang, migrations, assets) | OK |
| `admin:install` (migrate + seed + scaffold `app/Admin`) | OK |
| GET login, dashboard, users (list/create/edit/show), roles, permissions, menu, logs, setting | All **200** |
| Grid sort / filter / per-page, PJAX request | 200 |
| CSV export (`_export_=all`) | Valid CSV with BOM streamed |
| POST/PUT/DELETE user, POST menu, POST permission, PUT setting | Worked (302/200) |
| `admin:form`, `admin:action`, `admin:export-seed`, `admin:menu`, `admin:extend`, `admin:generate-menu` | OK |
| `admin:make`, `admin:controller` | **FAIL**: `Method Illuminate\Database\SQLiteConnection::isDoctrineAvailable does not exist` |
| `admin:permissions` | Fails on SQLite (`SHOW TABLES`); MySQL-only by design, pre-existing |

Two 500s seen during the experiment were **test-harness artifacts, not package bugs**, and are recorded so nobody chases them:
- `POST /menu` with integer `0` for `parent_id`: `0 == null` is true in PHP, tripping the "parent select error" guard in `ModelTree`. A real browser sends the string `"0"`.
- `DELETE /users/{id}`: the `admin` filesystem disk was not defined in the fresh skeleton, so `UploadField::$storage` stayed `''`. Adding the disk fixed it. (This is a documented install step, but see finding 4.6 for a robustness improvement.)

---

## 3. Findings

Severity: **B** = blocks install/boot, **H** = broken feature, **M** = deprecation or latent risk, **L** = cleanup / hygiene.

### 3.1 Dependency constraints (B)

`composer.json` today:

```
"php": ">=7.0.0"
"symfony/dom-crawler": "~3.1|~4.0|~5.0"
"laravel/framework": ">=5.5"
"doctrine/dbal": "2.*|3.*"
```

| Constraint | Problem | Required change |
|---|---|---|
| `symfony/dom-crawler ~3.1\|~4.0\|~5.0` | **Hard blocker.** Laravel 13 requires Symfony `^7.4 \|\| ^8.0`; there is no overlap. Composer cannot resolve. | `^7.4\|^8.0` (add `^6.4` only if Laravel 10/11 support is kept) |
| `symfony/css-selector` (missing) | `Pjax` middleware calls `Crawler::filter()`, which needs `symfony/css-selector`. Today it only arrives via dev tooling. A production install can fail at runtime on any PJAX request. | Add to `require` |
| `laravel/framework >=5.5` | Unbounded. Advertises support for versions never tested. | Pin to the supported range |
| `php >=7.0.0` | Wrong. | `^8.5` (decided: PHP 8.5 only) |
| `doctrine/dbal 2.*\|3.*` | Laravel 11+ removed DBAL integration; it is no longer needed by the framework. Only `ResourceGenerator` uses it (and it is broken on L11+ anyway). | Remove (see 3.2) |
| `require-dev: fzaninotto/faker` | Abandoned. Composer warns. | `fakerphp/faker` |
| `require-dev: laravel/laravel >=5.5` | Used as a test skeleton, not a proper approach. | `orchestra/testbench` (see 3.6) |
| `require-dev: laravel/browser-kit-testing ^6.0` | v6 targets older Laravel/PHPUnit. | `^7.2` resolves on L13 (7.2.8 resolved in dry run) |
| `require-dev: intervention/image ~2.3` | See 3.4. | Decision required |
| `require-dev: spatie/phpunit-watcher ^1.22` | Resolves (1.24.4). | Keep or drop |

`composer update --dry-run` against `laravel/framework:^13.0` with Symfony `^7.4|^8.0` and `doctrine/dbal ^4` resolved cleanly: Laravel 13.38.0, PHPUnit 13.4.3, browser-kit-testing 7.2.8, spatie/eloquent-sortable 5.0.1. So the dev tooling is available; only the production constraints need rewriting.

### 3.2 Doctrine DBAL removal: `admin:make` / `admin:controller` (H)

`src/Console/ResourceGenerator.php::getTableColumns()` uses `Connection::isDoctrineAvailable()`, `getDoctrineSchemaManager()` and `registerDoctrineTypeMapping()`. These were removed in Laravel 11. **Verified failure on 13.38.**

Replace with Laravel's native schema introspection (`Schema::connection(...)->getColumns($table)`, the native replacement used since Laravel 11). It returns arrays (`name`, `type_name`, `type`, `nullable`, `default`, `auto_increment`, ...), not `Doctrine\DBAL\Schema\Column` objects, so the consumers need to be updated:

- `generateForm()`, `generateShow()`, `generateGrid()` call `$column->getName()`, `$column->getType()->getName()` and `$column->getDefault()`.
- The `$doctrineTypeMapping` property (enum/geometry/point mapped to `string`) becomes unnecessary because native `type_name` already exposes these.
- `$fieldTypeMapping` / `$defaultFieldType` are keyed on Doctrine type names (`string`, `text`, `integer`, `bigint`, `smallint`, `float`, `decimal`, `boolean`, `date`, `datetime`, `time`, `timestamp`, ...). Native `type_name` values are driver-reported (`varchar`, `int`, `tinyint`, `datetime`, `text`, ...). **A new mapping table is required, not just a rename.** This is the one place where genuine behaviour design is needed.
- Respect table prefix and schema-qualified names (the current code splits `db.table`).

Related small items in the same area:
- `ExportSeedCommand` already uses `getSchemaBuilder()->getColumnListing()`: fine.
- `PermissionCommand::getAllTables()` uses `DB::select('SHOW TABLES')`: MySQL-only. Replace with `Schema::getTables()` (native, driver-agnostic).

### 3.3 PHP 8.4 / 8.5 deprecations (M)

**35 implicit-nullable parameter deprecations in 24 files** (PHP 8.4: `Closure $callback = null` must be `?Closure $callback = null`). Found by `php -l`; all re-confirmed at runtime during the smoke test. Examples: `Grid::__construct`, `Grid::init`, `Grid::rows`, `Form::__construct`, `Form::header/footer`, `Show::init`, `Tree::__construct`, `Admin::content/tree/navbar`, `Layout\Content::__construct`, `Form\Field::setForm/data`, `ImageField::thumbnail` (two params), `MultipleFile/MultipleImage::prepareForeach`, `Grid\Model::__construct/collection`, `Grid\Filter\Group::__construct`, `ModelTree::withQuery/selectOptions`, `NestedForm::setForm/setWidgetForm`, `Captcha::setForm`, `ValuePicker::mount/addPickBtn`, `HasValuePicker::mountPicker`, `Form\Builder::getResource`, `HasHooks::init`, `Grid\Concerns\HasHeader/HasFooter`, `BatchActions::add`, `AbstractExporter::__construct`. The complete list is reproducible with:

```
find src -name '*.php' -print0 | xargs -0 -n1 php -d error_reporting=-1 -l 2>&1 | grep Deprecated
```

Fix is mechanical and safe (add `?` to the type). Caveat: subclasses and user code that override these methods keep working (adding `?` to a parent is widening-compatible for parameters).

**Runtime deprecations (2):**
- `Encore\Admin\Tree::$path` is a **dynamic property** (`src/Tree.php:88`); deprecated since PHP 8.2, error in 9.0. Declare it. A wider scan for undeclared properties on other classes is a plan task, since a static grep cannot find them reliably.
- `preg_match(): Passing null to parameter #2` from `UrlGenerator::isValidUrl()`, triggered by `Administrator::getAvatarAttribute()` calling `url()->isValidUrl($avatar)` when `avatar` is `null`. Guard with `$avatar && ...`.

**Checked and clean:** `${var}` string interpolation, `utf8_encode/decode`, `strftime`, `FILTER_SANITIZE_STRING`, `each()`, `create_function`, `get_class()` without args, non-canonical casts (`(boolean)`, `(integer)`, `(double)`, `(binary)`), `ReflectionProperty::setAccessible`, backtick operator. No syntax errors on 8.5.

**To verify during testing (cannot be found statically):** `null` used as array key (deprecated in 8.5), `null` passed to internal string functions (the `preg_match` case above is one instance; `prepare_options()` in `src/helpers.php` calls `strpos($value, ...)` on non-array values that may be `null`), and more dynamic properties.

### 3.4 Image handling: Intervention Image v2 (M, decision required)

`ImageField` (and therefore `MultipleImage`) imports `Intervention\Image\Facades\Image` and `Intervention\Image\Constraint`, and calls `InterventionImage::make()`, `->resize/fit(...)->resizeCanvas(...)`, `->encode()`. That is the **v2** API. `intervention/image` is only in `suggest` / `require-dev`, never `require`.

- v2 (2.7.x) is unmaintained; it is a PHP 5-era codebase that will emit deprecations on 8.4/8.5 and is not guaranteed on 8.5.
- v3/v4 changed the API entirely (`ImageManager`, `read()`, `scale()/cover()`, encoders, no `Facades\Image`; the Laravel facade moved to `intervention/image-laravel`). A dry-run with an unconstrained requirement resolves to **4.4.0**, which would silently break this code.

Options (decide before coding):
1. **Keep v2** pinned (`~2.7`) and document the limitation. Cheapest. Risk: dead dependency on PHP 8.5.
2. **Migrate to v3/v4** via `intervention/image` + `intervention/image-laravel`. Recommended; the usage surface is small (one method, ~20 lines) but needs real image tests.
3. **Abstract behind an interface** and ship v2 and v3 drivers. Most work; only worth it for a public package.

### 3.5 Laravel 13 upgrade-guide items mapped to the package

Every item in the official 12.x to 13.x guide was checked against the code.

| Laravel 13 change | Impact on this package |
|---|---|
| CSRF middleware renamed `PreventRequestForgery` (adds `Sec-Fetch-Site` origin check); `VerifyCsrfToken` kept as deprecated alias | The package never references `VerifyCsrfToken` in `src/`. Admin POSTs with `_token` worked. **Risk:** the `Sec-Fetch-Site` check could reject cross-origin requests, for example if the admin is on a separate subdomain via `admin.route.domain` calling APIs on another origin. Test it. Update README/docs and test code that mention it. |
| `cache.serializable_classes` default `false` | The package does not cache PHP objects (verify the `Extension`/menu code paths). No change expected; document for users. |
| `session.serialization` default `json` | No impact on package; `MessageBag` objects are flashed to session (`admin_toastr`, `admin_info`, `Handler::error`, `ViewErrorBag`). **Verify** flash of `MessageBag` still round-trips under JSON session serialization (it is a common source of breakage). |
| `Container::call` nullable defaults | No impact found. |
| Domain route precedence | Possible effect if `admin.route.domain` is set: domain routes now win over non-domain routes. Test with a domain configured. |
| Pagination Bootstrap 3 view names changed | The package ships its own `admin::pagination` view and does not reference `pagination::default`. No impact; confirm `Paginator::links()` calls in grid. |
| Model booting: instantiating a model during `boot` throws `LogicException` | `ModelTree::boot()` registers `saving` callbacks and does not instantiate. Menu's static boot is OK (experiment passed). Re-verify after any change. |
| `JobAttempted`, `QueueBusy`, cache/queue/dispatcher contract additions, HTTP client signatures, `Str` factory reset, `Js::from` unicode | Not used by the package. |
| `symfony/polyfill-php85` globals (`array_first`, `array_last`) | The package defines global helpers (`array_delete`, `file_size`, `class_uses_deep`, `prepare_options`, `json_encode_options`). None collides today, but they pollute the global namespace and are guarded with `function_exists`. Keep the guards; consider namespacing in a future major. |
| `upsert` `uniqueBy` validation, `DELETE ... JOIN ORDER/LIMIT` on MySQL | Not used. |

### 3.6 Changes accumulated across Laravel 10, 11, 12 (since the package targets 9)

Because the package last targeted Laravel 9, changes from 10, 11 and 12 also apply. Items that touch the package:

| Version | Change | Package impact |
|---|---|---|
| L10 | PHP 8.1 minimum; native types added to many framework signatures | Subclass/override signatures compile fine today; re-check when overriding `Model` or `Command` methods. |
| L10/L11 | `$dates` removed from Model; `casts()` method | Package does not use `$dates`. `DefaultDatetimeFormat::serializeDate()` is fine (override is compatible). |
| L11 | Slim application skeleton: no `app/Http/Kernel.php`, no `RouteServiceProvider`, middleware registered in `bootstrap/app.php` | The package registers middleware via `Router::aliasMiddleware` and `middlewareGroup` in its provider and loads routes itself. **Works unchanged** (verified). `config/admin.php` uses the `web` group, which still exists. |
| L11 | Migrations: modifying a column must restate all attributes; `doctrine/dbal` removed | Package migration only uses `create`/`drop`. Unaffected. |
| L11 | Carbon 3 | `DefaultDatetimeFormat` uses `Carbon::DEFAULT_TO_STRING_FORMAT`. Verified working on Carbon 3.15. |
| L11+ | `Schema::getColumns/getTables` replace DBAL | See 3.2. |
| L12 | Local disk default root moved to `storage/app/private` | Package reads `admin.upload.disk` (default `admin`), which users define themselves. Update install docs and `tests/config/filesystems.php`. |
| L12 | `HasUuids` generates UUIDv7 | Not used. |
| L9+ | `lang/` at project root | Already handled (`registerPublishing` checks version >= 9). |

### 3.7 Other code-level observations (L)

- `src/AdminServiceProvider.php`:
  - `compatibleBlade()` uses reflection to check for `withoutDoubleEncoding`; always present now: call it directly.
  - `registerPublishing()` has a `version_compare(... '9.0.0')` branch; keep only the `lang_path()` branch.
  - `ensureHttps()` runs `request()->getRequestUri()` at boot (can misbehave in console/queue contexts); harmless today.
- `src/Traits/DefaultDatetimeFormat.php` and `src/Grid/Model.php::joinParameters()` contain `version_compare` branches for Laravel < 7 and < 5.8. Dead code now.
- `src/Console/stubs/*.stub` generate `use App\Http\Controllers\Controller;`. Works on the L11+ skeleton (base `Controller` still exists), but the L11+ base controller no longer extends the framework controller, so generated code must not rely on `$this->middleware()`.
- `routes.stub` and the admin `config('admin.route.namespace')` rely on the string `'Controller@method'` syntax plus group `namespace`. Still supported by the router in 13.x (route registered fine in the experiment), but is the legacy style; generated routes should move to `[Controller::class, 'method']`.
- `Pjax` middleware depends on `$response->exception`, a dynamic property set by the exception handler. Works on 13.38; fragile. Wrap in `property_exists`/`isset`.
- `Pjax::respond()` calls `exit` (also `CsvExporter` streams then exits). Works, but blocks Octane/long-running workers and makes testing hard.
- Global `exit` use and `Request` static facade use inside `ModelTree::boot()` (reads the HTTP request from inside model events) are architectural debts that will bite under Octane/queues. Not Laravel-13 blockers.
- The README and `docs/en` still say "PHP >= 7.0.0, Laravel >= 5.5".
- `.travis.yml` (Travis CI, PHP 7.2 to 8.0) is dead. There is `.github/` with only dependabot and stale configs, so **no working CI exists**.

### 3.8 Test suite (H)

`tests/` (17 files, 73 test methods) cannot run on a modern stack:

1. `tests/TestCase.php` boots `vendor/laravel/laravel/bootstrap/app.php`. That works for the Laravel 5 to 10 skeleton as a vendor package; Laravel 11+'s builder-style `bootstrap/app.php` with `basePath` inside `vendor/` is not a supported pattern. Move to **Orchestra Testbench** (v11.x exists on Packagist; confirm it matches Laravel 13 when implementing).
2. `tests/seeds/factory.php` uses the legacy `Illuminate\Database\Eloquent\Factory` and global `factory()` helper (removed in Laravel 8, available only via `laravel/legacy-factories`). Rewrite as class-based factories (`HasFactory`) on the test models.
3. `phpunit.xml.dist` uses removed attributes (`backupStaticAttributes`, `convertErrorsToExceptions`, `convertNoticesToExceptions`, `convertWarningsToExceptions`, `verbose`, `stopOnFailure` semantics). PHPUnit 10+ rejects or warns on them. Run `phpunit --migrate-configuration` and adjust.
4. Tests assume MySQL (`DB_CONNECTION=mysql`, raw MySQL `DELETE FROM migrations` statement in `tearDown`). Make DB configurable and prefer SQLite in CI, MySQL as a matrix leg.
5. `Laravel\BrowserKitTesting` v7.x works with PHPUnit 12/13 style, but the tests use `$this->visit()` style assertions; keep, since the package renders HTML pages.
6. PHPUnit: Laravel 13 recommends `^12.0`; the dry run picked 13.4.3. Pick one and let `browser-kit-testing` 7.x constraints decide.

### 3.9 Security and compliance observations

Raised proactively (organisation rule: flag privacy and security concerns):

- **`tests/config/filesystems.php`** contains what look like hard-coded cloud-storage credentials (access id/key style values for a Qiniu/OSS style disk). If they are real, treat them as leaked: rotate and replace with `[PLACEHOLDER]`/env lookups. They are not reproduced in this document.
- **Default admin credentials** `admin` / `admin` are seeded by `AdminTablesSeeder`; `admin:install` does not force a password change. For any deployment handling payment data (PCI-DSS scope, Bangladesh Bank requirements), require a password change on first login or generate a random one.
- **Password column** is `string(60)`: sufficient for bcrypt only. Laravel's default hasher remains bcrypt but Argon2/other hashes and `Hash::needsRehash` flows need a longer column. Widen to 255 in a new migration.
- **Operation log stores unredacted input.** `Middleware\LogOperation` writes `json_encode($request->input())` to `admin_operation_log` with no redaction (the `_token`/`_pjax` stripping in `LogController` is display-only). Passwords from the login and user forms, and any sensitive field on admin pages, are persisted in clear text. For deployments in PCI-DSS or Bangladesh Bank scope this is a data-protection defect. Add a configurable redaction list (`password`, `password_confirmation`, `_token`, anything PAN-like) and apply it before the insert; also review retention.
- **Front-end vendor assets** (`resources/assets`): AdminLTE 2, Bootstrap 3, jQuery (version unverified), moment, sweetalert2, etc. are bundled and unmaintained; they likely carry known XSS CVEs. Inventory the versions and update or replace. This is separate from Laravel 13 compatibility but is the biggest remaining security debt.
- **Laravel 13 `PreventRequestForgery`** and `session.serialization=json` are security improvements the package should be tested against (see 3.5), not opted out of.
- Session/auth config is merged at runtime via `config(Arr::dot(config('admin.auth'), 'auth.'))` (`AdminServiceProvider::loadAdminAuthConfig`). It mutates global `auth.*` config for the whole application in `register()`; `Authenticate` middleware also sets `auth.defaults.guard=admin` per request. Under Octane this leaks the guard default across requests. Acceptable for classic FPM; flag for long-running servers.

---

## 4. Support range (decided)

**Laravel `^13.0` and PHP `^8.5` only.** Released as a new major (`3.x`); Laravel 9 users stay on the old tag.

Consequences:
- No legacy branches: every `version_compare` / `method_exists` compat shim can be deleted.
- PHP 8.5 syntax and features may be used (for example the pipe operator, `array_first()`/`array_last()`, `#[\NoDiscard]`). The `symfony/polyfill-php85` global-function conflict noted in 3.5 does not apply at runtime on 8.5, since the native functions exist; keep avoiding global helpers with those names.
- Only one PHP and one Laravel version in CI; the matrix is databases only.
- Packages that require older PHP cannot be co-installed. Composer will refuse them, which is intended.
- The implicit-nullable fixes in 3.3 are still required (they are PHP 8.4+ deprecations, so they apply on 8.5).

## 5. Risk register

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R1 | Untested form field types, tree, nested forms, uploads behave differently on Laravel 13 | Medium | Medium | Restore the test suite first; add smoke coverage per field type |
| R2 | Intervention Image decision (3.4) | High (decision pending) | Medium | Choose option 2 and add upload/thumbnail tests |
| R3 | `PreventRequestForgery` origin check on multi-origin deployments | Low-Medium | High (admin unusable) | Test with `admin.route.domain` and reverse proxies |
| R4 | JSON session serialization vs flashed `MessageBag` | Medium | Low-Medium | Dedicated test; fall back to arrays if it fails |
| R5 | Hidden dynamic properties / null-to-string deprecations on 8.5 | Medium | Low | Run the suite with `error_reporting=-1` and fail on deprecations |
| R6 | MySQL/PostgreSQL behaviour differs from the SQLite smoke test | Medium | Medium | CI matrix with MySQL 8 and PostgreSQL |
| R7 | Downstream extensions (laravel-admin-ext/*) break | High | Medium | Out of scope; document breaking changes and keep public method signatures where possible |
| R8 | Bundled front-end libraries carry known vulnerabilities | High | Medium-High | Separate hardening track (migration plan, Phase 7) |

---

## 6. Appendix: deprecation hotspots by file

`php -l` on PHP 8.5.11 reported implicit-nullable deprecations at these locations (35 total in 24 files):

```
src/Admin.php:109,136,267
src/Form.php:124,1316,1426
src/Form/Builder.php:233
src/Form/Concerns/HasHooks.php:30
src/Form/Field.php:498,870
src/Form/Field/Captcha.php:23
src/Form/Field/HasValuePicker.php:48
src/Form/Field/ImageField.php:107 (2 params)
src/Form/Field/MultipleFile.php:179
src/Form/Field/MultipleImage.php:30
src/Form/Field/ValuePicker.php:75,97
src/Form/NestedForm.php:169,183
src/Grid.php:175,205,606
src/Grid/Concerns/HasFooter.php:22
src/Grid/Concerns/HasHeader.php:22
src/Grid/Exporters/AbstractExporter.php:24
src/Grid/Filter/Group.php:35
src/Grid/Model.php:102,282
src/Grid/Tools/BatchActions.php:79
src/Layout/Content.php:48
src/Show.php:104
src/Traits/AdminBuilder.php:39
src/Traits/ModelTree.php:123,239
src/Tree.php:84 (2 params)
```

Runtime-only: `src/Tree.php:88` (dynamic property `$path`), `src/Auth/Database/Administrator.php` `getAvatarAttribute` (null into `isValidUrl`).
