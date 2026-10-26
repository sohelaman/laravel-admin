# Outcome: Laravel 13 / PHP 8.5 upgrade, front-end upgrade, test run and review

Date: 2026-10-27 (final run after the MySQL test and the `MIGRATING.md` guide)
Environment: `devbox` container, PHP 8.5.11, Composer 2.10, Laravel 13.38.0, PHPUnit 13.4.3, Node 22 (npm 10, used for the front-end audit only).
State: all changes are in the working tree, **nothing is committed** (85 changed files in `src` and `tests`, 195 in `resources/assets`, plus configuration and documentation).

## 1. Verdict

The package installs and runs on Laravel `^13.0` / PHP `^8.5`, the test suite passes on SQLite and on MySQL 8.4 (117 tests each, no deprecations, no warnings) and is stable in random order. The review found and fixed several defects that the suite did not cover, three of them security issues (section 4).

It is **not release-ready** until the items marked "before release" in section 5 are done: CI has never run, PostgreSQL has not been tested, and the fork's package name and version are not decided.

## 2. Test run

| Check | Result |
| --- | --- |
| `vendor/bin/phpunit` (SQLite) | **OK (117 tests, 1125 assertions)**, exit 0 |
| `vendor/bin/phpunit` (MySQL 8.4) | **OK (117 tests, 1125 assertions)**, default order; random order (SQLite, seed 17) also OK |
| Strictness | `failOnDeprecation`, `failOnWarning`, `failOnNotice`, `failOnRisky` are on; PHPUnit's own error handler is restored so Laravel does not swallow deprecations |
| Random order | `--order-by=random` with 11 different seeds: all green after the fixes of section 4.1 (several early runs failed, see 4.1) |
| Repeatability | 3 consecutive default runs and 6 random runs give identical assertion counts (the factories are seeded) |
| Lint | `php -l` with `error_reporting=-1` on `src`, `tests`, `database`, `config`: no syntax error, no deprecation (35 implicit-nullable parameters existed before) |
| `composer validate --strict` | valid |
| `composer audit` | no security advisories for the PHP dependencies |
| `composer update --prefer-lowest --dry-run` | resolves (the suite was **not** run on the lowest versions) |
| Front-end `npm audit` of the shipped libraries | 1 moderate advisory left: Bootstrap 3.4.1 (end of life, no fix) |
| Workflow files | `.github/workflows/tests.yml` and `dependabot.yml` parse as YAML; the workflow was **not executed** |

Database: SQLite in memory for the default run. **MySQL 8.4 was also tested** (2026-10-27): the same 117 tests pass against a throwaway `mysql:8.4` container (default order, and random order with seed 5), with the server confirming about 1900 `CREATE TABLE` statements per run. Command: `DB_CONNECTION=mysql MYSQL_HOST=127.0.0.1 MYSQL_PORT=33306 MYSQL_USER=root vendor/bin/phpunit` (the base class now reads `MYSQL_PORT` / `PGSQL_PORT`). The container was removed afterwards. PostgreSQL has not been run. The suite only drops tables of a database whose name contains `test`.

Tests by area (108 methods plus data sets; the displayers test gained a case for `HtmlString` in `label()`/`badge()`, which the earlier `UPGRADE.md` promised but the code did not honour): Assets 4, Auth 6, Console 4, File upload 7, Flash messages 7, Grid displayers 5, Handle controller 5, Image upload 9, Index 2, Install 1, Menu 6, Model tree 1, Operation log 5, Pjax 3, Permissions 7, Roles 5, Thumbnails 3, User form 7, User grid 14, User setting 5, Users 4.

## 3. What was verified outside the suite

- A throwaway Laravel 13 application (file sessions, JSON session serialization, SQLite) with the package, used for login, flash messages, the console commands and requests through the HTTP kernel.
- The UI in Firefox against that application, with a page of about 50 form fields, a grid, the menu tree and the user edit form: a probe collected `error`, `unhandledrejection`, `console.error` and `console.warn` (no entries). Interactions checked: select2, datetimepicker, icon picker, colour picker, switch, iCheck, input masks, dual listbox, file inputs (single, multiple, previews), x-editable, dropdown actions, sweetalert2 confirm/cancel/success, toastr, nestable, sidebar tree, push-menu toggle and PJAX navigation. Screenshots before and after were compared.
- jQuery Migrate 3.6 was loaded once to list deprecated calls: only things that matter for jQuery 4 (event shorthands, `$.isFunction`, `$.trim`), made by plugins.

## 4. Review findings

### 4.1 Fixed during this review (tests added for each)

| # | Severity | Finding | Fix |
| --- | --- | --- | --- |
| 1 | **High** | **SQL injection through the grid sort.** `_sort[column]` (with a dot), `_sort[cast]` and `_sort[type]` were written into `JSON_EXTRACT(...)` / `orderByRaw` unescaped. Any user allowed to see a grid could inject SQL. | `Grid\Model::setSort()` accepts only identifier-shaped columns, `asc`/`desc`, and a list of casts; anything else is ignored. `UserGridTest` |
| 2 | **High** | **Arbitrary class instantiation and reflected XSS in `HandleController`.** `_handle_form_`, `_handle_action_`, `_handle_selectable_`, `_handle_renderable_` instantiated any class named in the request (`new $class(...$args)` with request arguments for selectables) and echoed the requested name back as HTML. | The class must extend/implement the expected type (`Widgets\Form`, `Actions\Action`, `Grid\Selectable`, `Renderable`), otherwise 404 / exception without reflecting input. `HandleControllerTest` |
| 3 | **High** | **Uploads could store `.php` files** (or `shell.php.jpg`, `.htaccess`) in the public upload disk; the extension is chosen by the client. | `UploadField::guardExtension()` checks every part of the client name and the guessed extension against `upload.forbidden_extensions` (default list in code and in `config/admin.php`). Validation error, nothing written. `FileUploadTest` |
| 4 | High | Stored XSS in the grid displayers (`label`, `badge`, `link`, `button`, `prefix`, `suffix`, `limit`, `secret`, `copyable`, `qrcode`, `downloadable`, `image`): they printed the raw database value. `link()` also accepted `javascript:` urls. | Output escaped with `e()` (an `HtmlString` still passes), dangerous url schemes replaced. `GridDisplayerTest` |
| 5 | High | With Laravel 13's default JSON session serialization, flashed `MessageBag` objects came back as arrays and crashed every page after a flash. | Messages are flashed as arrays and rebuilt with `admin_message_bag()`. `FlashMessageTest` |
| 6 | Medium | The operation log stored request input unredacted (passwords included); an array value under a sensitive key escaped the redaction. | Redaction of `password`, `password_confirmation`, `current_password`, `_token` plus `operation_log.redact`, key checked before recursing. `OperationLogTest` |
| 7 | Medium | Toastr flash messages were written inside a JS string without encoding; the type was not validated. | `@json` and an allow-list. `FlashMessageTest` |
| 8 | Medium | No brake on password guessing on the login form. | 5 failed attempts lock the user out for 60 seconds (`RateLimiter`). `AuthTest` |
| 9 | Low | Sidebar search used the typed text as a regular expression; the carousel id was printed unescaped; the code generator wrote database defaults unescaped into PHP source. | Escaped. |
| 10 | Test quality | The suite was order dependent: `MenuTest::testMenuIndex` asserted on a string that only appeared through static `Admin` state left by earlier tests; `testLikeFilter` depended on random faker data (flaky); the first test of a process saw untranslated strings; a new upload test depended on a directory existing. | Admin static state is reset around each test, faker is seeded, the translator cache is flushed after publishing, assertions corrected. |

Also corrected in this pass: stale statements in `README.md` and `docs/en/README.md` (PHP 7 / Laravel 5.5, Travis badge).

### 4.1b External sources removed (2026-10-27)

The front-end was scanned for anything loaded from another origin. Removed: `html5shiv`/`respond.js` from a CDN, the QR code service (`api.qrserver.com`, which received the cell content), Gravatar (leaked email hashes), CKEditor 4.5 from `cdn.ckeditor.com` (replaced by a local Summernote 0.9), and the map provider script that was added to every admin page when the `map` field was registered (now opt-in through `allow_external_map_api`). `tests/AssetsTest.php` enforces it (source scan, stylesheet scan, crawl of the admin pages). Details: `docs/frontend-dependency-audit.md` section 6b and `UPGRADE.md`.

### 4.2 Fixed earlier (see `docs/` for details)

DBAL removal from the generators, 35 PHP 8.4 deprecations, `Tree::$path`, null-to-string calls, `HTML-ENTITIES`, translation publishing path (`lang_path()`), Pjax guards, Intervention Image 4 migration, test suite rebuilt on Testbench, 14 front-end libraries upgraded (jQuery 3.7.1, Bootstrap 3.4.1, AdminLTE 2.4.18, select2 4.1.0, sweetalert2 11, fileinput 5, inputmask 5, moment 2.31, ...), UI code adapted to them.

## 5. Open items and residual risks

Before release:

1. **CI has never run** (set aside for now). The GitHub Actions workflow is untested. MySQL 8.4 passes locally; PostgreSQL behaviour (column types reported by `Schema::getColumns()`, JSON sorting, collations) is unverified.
2. **Package identity.** `composer.json` still says `encore/laravel-admin`, the author's name and homepage. Choose the fork's vendor/name, tag `3.0.0`.
3. **Run the suite once on the lowest dependency versions** (`composer update --prefer-lowest`), the constraints only resolve.
4. **Upload field without disk.** If the `admin` disk is not configured, `UploadField::$storage` stays an empty string; only `destroy()` was guarded, saving a file still fatals. Throw a clear configuration exception instead.
5. **Default credentials.** `admin:install` still seeds `admin` / `admin` (it now prints a warning). Prefer a prompt or a generated password.

Known limitations (accepted or out of scope):

6. **Bootstrap 3 is end of life.** One moderate advisory (tooltip/popover/carousel `data-*`) cannot be fixed on 3.x. Mitigated in the package code (see `docs/frontend-dependency-audit.md`); removal needs Bootstrap 5 / AdminLTE 4, a rewrite of every view that breaks extensions.
7. **Content widgets are raw HTML by design** (`display`, show fields, `Box`, `Callout`, `Collapse`, `Tab`, `Alert`, flash message bodies). The developer decides what goes in; untrusted data must be escaped by the caller.
8. **Everything that is not covered by a test:** most of the 60 form field types, nested/`HasMany` forms, orderable grids, the tree drag and drop, the Excel exporter (needs `maatwebsite/excel`), `admin:minify` with the new assets, `admin:extend`/`admin:import`, Octane (global `exit` in the PJAX and CSV paths, static state, per-request `config()` mutation).
9. **Behaviour changes users will notice** (all in `UPGRADE.md`): Laravel 13 and PHP 8.5 only, Intervention Image 4 (the 2.x API is gone), generator output depends on native column types, grid displayers escape their values, uploads of scripts are refused, failed logins are throttled, the sort input is validated, currency masks and the slider look different, assets must be published again.
10. **Minor:** the bundled minified files reference source maps that are not shipped (404 in the browser dev tools only); the console route stubs still use the `'Controller@method'` style (supported by Laravel 13); logout is a `GET` route; `_handle_*` routes skip the permission middleware (each action authorizes itself); only the English language file received the new `upload_forbidden_extension` message (other locales fall back to English when the application fallback locale is `en`); the demo application and the front-end verification environment live only in the session scratchpad.

## 6. Suggested next steps

1. Run the suite against PostgreSQL (and later let the workflow run); MySQL already passes.
2. Decide the package name and tag the release; publish the assets in a real application with `php artisan admin:publish --force` and click through it once.
3. Decide whether Bootstrap 5 / AdminLTE 4 is worth a separate major release.
4. Add tests for the untested areas of item 8, starting with nested forms and the tree.

## 7. Where to find the details

- `docs/laravel13-php85-compatibility-analysis.md`, `docs/laravel13-php85-migration-plan.md` (analysis, plan, implementation status)
- `docs/frontend-dependency-audit.md`, `resources/assets/VERSIONS.md` (front-end audit and inventory)
- `MIGRATING.md` (step-by-step guide for a project built on 1.8.19: replacing the package and the changes required in its code)
- `UPGRADE.md` (all behaviour changes), `README.md`, `docs/en/installation.md`
- `tests/` (suite, factories under `tests/factories`, base class `tests/TestCase.php`)
