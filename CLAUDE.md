# CLAUDE.md

Guidance for coding agents working in this repository. Read this first, then the documents it links.

## Project overview

`encore/laravel-admin` is an administrative interface builder for Laravel: you describe CRUD screens with `Grid`, `Form`, `Show` and `Tree` builders and the package renders them (AdminLTE 2 / Bootstrap 3 / jQuery UI) together with users, roles, permissions, menu, operation log and a login.

This repository is a **fork** (`origin`: `sohelaman/laravel-admin`, working branch `revive`) of the original package, which is no longer maintained. The fork starts at upstream tag **v1.8.19**. Owner: SSLWIRELESS, a PCI-DSS certified payment gateway in Bangladesh, so security, data privacy and Bangladesh Bank compliance matter: never write real card numbers, tokens or credentials anywhere (use `[PLACEHOLDER]`), flag privacy concerns.

Goal of the fork (`NOTES.md`): keep the package usable on **Laravel 13 and PHP 8.5 only**, with front-end dependencies updated to safe versions.

Current state (see `OUTCOME.md` for the details):
- Upgrade work is done and **uncommitted in the working tree**; the maintainer reviews it before committing.
- Suite: 117 tests, green on SQLite and on MySQL 8.4, stable in random order. PostgreSQL and the CI workflow (`.github/workflows/tests.yml`) have never been run.
- Open items: fork package name and release tag, PostgreSQL run, Bootstrap 3 end-of-life advisory, untested areas (section "Known gaps").

## Documents (where to look)

| File | Content |
| --- | --- |
| `OUTCOME.md` | Final test run, code review findings (what was fixed, what is open), next steps. Start here for status. |
| `MIGRATING.md` | Step-by-step guide for a project built on 1.8.19: replacing the package and the changes needed in its code. |
| `UPGRADE.md` | Every behaviour change of this fork (requirements, Intervention 4, console, session, security hardening, front-end). Keep it current when behaviour changes. |
| `docs/laravel13-php85-compatibility-analysis.md` | Analysis of what blocked/broke on Laravel 13 / PHP 8.5, findings table. |
| `docs/laravel13-php85-migration-plan.md` | Phased plan with an implementation status table. |
| `docs/frontend-dependency-audit.md`, `resources/assets/VERSIONS.md` | Front-end audit, library inventory and how each was adapted. |
| `docs/en/*` | Original user documentation (API of grids, forms, fields...). Mostly still valid; installation and README were updated. |
| `NOTES.md` | The maintainer's target list. |
| `.claude/memory/` | Project memory (`MEMORY.md` index + notes on overview, working rules, testing workflow, front-end workflow, migration guide). Versioned in git; copy changes to your own Claude memory directory if you want both in sync. |

## Environment rules (important)

- The host has **no PHP, Composer or Node**. Run every PHP/Composer/Node command inside the `devbox` distrobox container: `distrobox enter devbox -- <command>` (PHP 8.5.11, Composer 2.10, Node 22 are there). Docker, .NET and Java run on the host.
- Never install system packages or new global tools yourself; tell the maintainer what is needed.
- **Never commit or create branches without explicit approval.** Leave changes in the working tree.
- Do not kill processes you did not start; do not run tests against a database you do not own (the suite drops tables).
- Use `XDEBUG_MODE=off` when running PHPUnit (Xdebug is installed and prints huge traces otherwise).

## Commands

```bash
# install / update dependencies (composer.lock is git-ignored)
distrobox enter devbox -- composer update

# full test suite (SQLite in memory)
distrobox enter devbox -- bash -c 'XDEBUG_MODE=off vendor/bin/phpunit --no-progress --colors=never'
# one file / one test / random order
distrobox enter devbox -- bash -c 'XDEBUG_MODE=off vendor/bin/phpunit tests/AuthTest.php --filter testLogin'
distrobox enter devbox -- bash -c 'XDEBUG_MODE=off vendor/bin/phpunit --order-by=random --random-order-seed=7'

# MySQL: start a disposable container (image mysql:8.4 is already pulled), run, remove it
docker run -d --name la-mysql-test -p 127.0.0.1:33306:3306 -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=laravel_admin_test mysql:8.4
distrobox enter devbox -- bash -c 'DB_CONNECTION=mysql MYSQL_HOST=127.0.0.1 MYSQL_PORT=33306 MYSQL_USER=root XDEBUG_MODE=off vendor/bin/phpunit'
docker rm -f la-mysql-test
# PostgreSQL: DB_CONNECTION=pgsql PGSQL_HOST/PGSQL_PORT/PGSQL_USER/PGSQL_PASSWORD (never run yet)

# syntax + deprecation sweep (must print nothing)
distrobox enter devbox -- bash -c 'find src tests database config -name "*.php" -print0 | xargs -0 -n1 php -d error_reporting=-1 -d display_errors=1 -l 2>&1 | grep -v "^No syntax errors"'
distrobox enter devbox -- composer validate --strict
```

The suite is strict: `phpunit.xml.dist` fails on deprecations, warnings, notices and risky tests. A green run means **zero** PHP 8.5 deprecations.

## Architecture

- `src/Admin.php` (facade `Encore\Admin\Facades\Admin`) holds static asset/script state (`Traits/HasAssets.php`), registers auth routes and menu. `AdminServiceProvider` registers middleware (`admin.auth`, `admin.pjax`, `admin.log`, `admin.permission`, `admin.bootstrap`), the `admin` middleware group, commands, publishing, Blade directives and `Router::macro`s.
- Builders: `Grid.php` (+ `Grid/` columns, displayers, filters, tools, exporters, actions), `Form.php` (+ `Form/Field/*`, ~60 field types, each declares its `$js`/`$css` assets and renders a Blade view plus an inline script), `Show.php`, `Tree.php` (+ `Traits/ModelTree`).
- `Actions/` = row/batch/grid actions with `Interactor` (dialog/form) JS; routes `_handle_form_`, `_handle_action_`, `_handle_selectable_`, `_handle_renderable_` are served by `Controllers/HandleController`.
- `Auth/Database/*` models (Administrator, Role, Permission, Menu, OperationLog) and seeder; `Middleware/*`; `Console/*` artisan commands and `stubs/`; `Controllers/*` built-in pages (users, roles, permissions, menu, logs, auth).
- Views in `resources/views`, translations in `resources/lang`, config template in `config/admin.php`, migration in `database/migrations`.
- Front-end assets are **committed** in `resources/assets` (published to `public/vendor/laravel-admin`); PHP classes reference them by path (`'/vendor/laravel-admin/...'`).
- Inline JavaScript is generated from PHP heredocs (`Admin::script(...)`); remember `$` and `{$var}` rules of PHP heredocs/nowdocs.

## Code conventions

- Match the surrounding code (original author's style, StyleCI/Laravel preset): 4 spaces, PSR-4 `Encore\Admin\`, short arrays, aligned `=>` in multi-line arrays, no space after `!`, docblocks with `@param`/`@return` in the existing format, comments sparse. No PHP-CS-Fixer is installed; copy the style of neighbouring files.
- Targets PHP 8.5: optional typed parameters must be explicit nullable (`?Closure $callback = null`); declare properties (no dynamic properties); do not pass `null` to string functions.
- **No external assets**: never add a CDN/third-party script, style, font, image or API call (tests enforce it); vendor the library into `resources/assets` instead. The `map` field is the only, opt-in, exception.
- Escape output. Blade `{!! !!}` is only for HTML that the developer wrote. Anything coming from the database, the request or flash messages must be escaped (`e()`, `{{ }}`, `@json`). Grid displayers escape their value (an `HtmlString` passes through). Never write request input into SQL, JS or class names unvalidated.
- Flash messages are stored as **arrays** (`admin_toastr`, `admin_success`..., `Exception\Handler::error`) because Laravel 13 sessions are JSON; read them with `admin_message_bag()`.
- Use `$.admin.swal(...)` (wrapper of sweetalert2 11) rather than the `swal` global in new JS; `$.isArray`/`.unbind` style jQuery-3-deprecated calls should not be added.
- Keep `UPGRADE.md` (and `MIGRATING.md` if users must act) in sync with any behaviour change; add `docs/` notes for notable findings.

## Tests

- Base class `tests/TestCase.php` boots an **Orchestra Testbench** app (no `laravel/laravel` skeleton), points `app_path`, `config_path`, `public_path`, `lang_path`, `database_path` at a per-process temp directory, uses the package's own `config/admin.php` plus `tests/config/filesystems.php`, publishes the package, runs `admin:install` and the test migrations. Tests use BrowserKit (`visit`, `see`, `press`, `submitForm`).
- Gotchas learned the hard way: `Admin` has static state, `TestCase` resets it around every test; faker is seeded (`fake()->seed`); the app env is forced to `testing` so CSRF is skipped; the translator is rebuilt after publishing; DB tables are only dropped on `:memory:` or a database name containing `test`.
- Test models/controllers/factories live in `tests/models`, `tests/controllers`, `tests/factories` (routes in `tests/routes.php`). Fixtures in `tests/assets` (do not upload `.php` fixtures, the package refuses them).
- Write a test for every fix, especially for security fixes (see `GridDisplayerTest`, `HandleControllerTest`, `FileUploadTest`, `OperationLogTest`, `FlashMessageTest`, `UserGridTest::testSortInputIsNotWrittenInTheQuery`).
- `tests/AssetsTest.php` fails if the sources reference an asset that is not shipped or if an old library version comes back.

## Front-end assets

- Inventory with versions: `resources/assets/VERSIONS.md`. Stack: jQuery 3.7.1, Bootstrap 3.4.1, AdminLTE 2.4.18 (deliberate: Bootstrap 5 / AdminLTE 4 / jQuery 4 would rewrite every view and break extensions).
- To update a library: install the npm package in a scratch directory (inside `distrobox enter devbox`), copy only the dist files to the same path in `resources/assets`, update the path constants in PHP (`Traits/HasAssets.php`, the field classes), adapt the JS call sites, update `VERSIONS.md`, run `npm audit` on the shipped set and `tests/AssetsTest.php`.
- UI checks used so far: a throwaway Laravel 13 app with the package symlinked (`composer` path repository), a demo page with ~50 fields, and the Firefox DevTools MCP with an error probe (`window.__errs`). Chrome is not installed.

## Known gaps / next steps

1. Run the suite on PostgreSQL; run the GitHub workflow (maintainer set CI aside for now).
2. Choose the fork's package name/vendor and release tag (`composer.json` still says `encore/laravel-admin` and the original author).
3. `UploadField`: if the upload disk is not configured, saving a file fatals (only `destroy()` is guarded): throw a clear configuration exception.
4. Default `admin`/`admin` account is seeded at install (warning printed).
5. Untested: most field types, nested/`HasMany` forms, orderable grid, tree drag and drop, Excel exporter, `admin:minify`, `admin:extend`/`admin:import`, Octane.
6. Bootstrap 3 EOL advisory (one moderate, unfixable on 3.x).
7. Minor: console route stubs use the old `'Controller@method'` syntax; logout is a GET route; minified libs reference source maps that are not shipped; `Admin::VERSION` still says `1.8.17`.
