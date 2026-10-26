# Migration Plan: Laravel 13 / PHP 8.5

Companion to [laravel13-php85-compatibility-analysis.md](laravel13-php85-compatibility-analysis.md). Finding references like "(A 3.2)" point to sections of that report.

**Decided scope:** Laravel `^13.0` and PHP `^8.5` only, released as a new major (`3.0.0`). No multi-version support, so there is no compatibility matrix. Commands run inside the `devbox` distrobox (PHP 8.5.11, Composer 2.10 are available there), per the dev-machine notes.

**Working agreement:** one logical change per commit, on a dedicated branch, committed only when you ask. Nothing here has been applied to the repository; this is a plan.

Order matters: Phase 1 gives a safety net, Phases 2 to 5 change code against it, Phases 6 to 8 finish and harden.

---

## Phase 0: Decisions and setup (0.5 day)

1. Support range is decided (Laravel ^13, PHP ^8.5). Confirm the new version number (`3.0.0`; the Laravel 9 line stays on the old tag).
2. Decide the Intervention Image strategy (A 3.4): keep v2 pinned, or migrate to v3/v4 + `intervention/image-laravel`. **Plan assumes migrate.**
3. Create a working branch (needs your approval before creating).
4. Check the repo state: `docs/zh/*` shows as deleted in the working tree right now; decide whether that is intentional before any commit.
5. Record the baseline: note that the current suite cannot run (A 3.8), so "green" means the *new* suite from Phase 1.

**Exit criteria:** range, version and Intervention decisions written down.

---

## Phase 1: Test harness first (2 to 3 days)

Goal: a suite that runs on Laravel 13 / PHP 8.5 before touching `src/`, so every later phase has a safety net. Expect failures at first; they are the work list.

1. **Dependencies for dev only** (`require-dev`), leaving `require` for Phase 2:
   - `orchestra/testbench` (the major matching Laravel 13; confirm on Packagist), `laravel/browser-kit-testing ^7.2`, `fakerphp/faker`, `phpunit/phpunit` (the version those packages allow).
   - Remove `laravel/laravel` and `fzaninotto/faker` from `require-dev`.
2. **Rewrite `tests/TestCase.php`** on Testbench:
   - `getPackageProviders()` returns `Encore\Admin\AdminServiceProvider`; `getPackageAliases()` adds the `Admin` alias.
   - Move config setup (DB, `admin` config, `auth.*` dot-merge, `filesystems` with an `admin` disk) into `defineEnvironment()`.
   - Keep BrowserKit visit-style assertions via `Laravel\BrowserKitTesting\TestCase` (check the Testbench + BrowserKit integration; a small base-class trait may be required).
   - Replace `require vendor/laravel/laravel/bootstrap/app.php` entirely.
3. **DB:** default to SQLite in-memory; allow MySQL/PostgreSQL through env vars. Replace the MySQL-specific `DB::select("delete from \`migrations\`...")` in `tearDown` with `DB::table('migrations')->where(...)->delete()`. Drop `Schema::defaultStringLength(191)` unless MySQL leg needs it.
4. **Factories:** delete `tests/seeds/factory.php`; add `HasFactory` + class factories for `Tests\Models\User`, `Profile`, `Tag` (and others used); replace `factory(...)` calls in `UserFormTest`, `UserGridTest`.
5. **`phpunit.xml.dist`:** run `phpunit --migrate-configuration`, then hand-edit: strict deprecation reporting (`failOnDeprecation`, `failOnWarning`, `displayDetailsOnTestsThatTriggerDeprecations`) turned **on from the start** (this is how Phase 3 findings are enforced).
6. **Secrets:** replace the hard-coded cloud credentials in `tests/config/filesystems.php` with placeholders or env lookups; rotate them if real (A 3.9).
7. Run the suite once and triage: *fails because of harness* vs *fails because of package*. Fix the first kind here; log the second kind as work items for Phases 2 to 5. Mark genuinely un-portable tests `skip` with a reason, never delete silently.

**Exit criteria:** suite executes end to end on PHP 8.5 + Laravel 13; failures are categorised.

> Note: the smoke test in the analysis passed all core pages, so most remaining failures should be harness-side. If more than a handful are package-side, revisit risk R1.

---

## Phase 2: `composer.json` and platform constraints (0.5 day)

Edit `composer.json` (A 3.1):

```json
"require": {
    "php": "^8.5",
    "laravel/framework": "^13.0",
    "symfony/dom-crawler": "^7.4|^8.0",
    "symfony/css-selector": "^7.4|^8.0"
},
"suggest": {
    "intervention/image": "...",
    "intervention/image-laravel": "...",
    "spatie/eloquent-sortable": "...",
    "maatwebsite/excel": "Required by Grid\\Exporters\\ExcelExporter."
}
```

- Remove `doctrine/dbal` from `require` (after Phase 4 step 1).
- `maatwebsite/excel`: `ExcelExporter` references it, but it is not declared anywhere. Add it to `suggest` with the version compatible with Laravel 13, and note it in the docs.
- Bump `extra.laravel` only if provider/alias discovery changes (it does not).
- Remove `.travis.yml` (dead) in Phase 6 when GitHub Actions is added.

Verify: `composer validate --strict`, `composer update --dry-run`, then a real `composer update` and `composer why-not laravel/framework 13` in an empty throwaway directory.

**Exit criteria:** `composer install` succeeds on PHP 8.5, and `composer install` on PHP 8.4 is refused (intended).

---

## Phase 3: PHP 8.4 / 8.5 deprecation cleanup (1 day)

1. **Implicit nullables (35 locations, 24 files, A 3.3 and appendix):** change `Closure $x = null` to `?Closure $x = null` (and the same for `Grid`, `Form`, `BatchAction`, `int $width = null`, and so on). Mechanical. Use Rector (`ExplicitNullableParamTypeRector`) **only as a one-off run from the devbox without adding it as a dependency** (do not install tools without asking), or edit by hand or with a scripted `sed` reviewed in the diff.
2. **`src/Tree.php`:** declare `protected $path;` (dynamic property).
3. **`src/Auth/Database/Administrator.php`:** `getAvatarAttribute`: guard `null`/empty before `url()->isValidUrl($avatar)`.
4. **`src/helpers.php::prepare_options()`:** guard `strpos($value, ...)` against non-string values.
5. **Static scan for hidden issues**: repeat the `php -l` sweep and grep for `(boolean)`, `(integer)`, `(double)`, `utf8_*`, `${`, `$http_response_header`, and `get_class()`.
6. **Runtime sweep:** run the Phase 1 suite with deprecations as failures. For paths no test covers (null array keys, undeclared properties), add targeted tests or rely on Phase 7's manual pass.

Verify: `find src tests database config -name '*.php' -print0 | xargs -0 -n1 php -d error_reporting=-1 -l 2>&1 | grep -E 'Deprecated|Fatal|Parse'` returns nothing.

**Exit criteria:** zero deprecations on PHP 8.5 over the full suite; only PHP 8.5 is supported, so no other PHP versions are tested.

---

## Phase 4: Laravel 11/12/13 API changes (2 to 3 days)

### 4.1 Replace Doctrine DBAL in the generator (A 3.2), the main code change
1. Rewrite `ResourceGenerator::getTableColumns()` to use `Schema::connection($connection)->getColumns($table)` (table prefix and `schema.table` handling kept).
2. Change `generateForm/Show/Grid` to read array keys (`name`, `type_name`, `default`, `nullable`, `auto_increment`) instead of Doctrine `Column` methods.
3. Replace `$doctrineTypeMapping` and rebuild `$fieldTypeMapping` / `$defaultFieldType` keyed on **native type names** (`varchar`, `char`, `text`, `longtext`, `int`, `bigint`, `tinyint`, `smallint`, `decimal`, `float`, `double`, `date`, `datetime`, `timestamp`, `time`, `json`, `enum`, `boolean`, and so on). Define behaviour for unknown types (fall back to text).
4. Unit-test the generator against SQLite **and** MySQL, because `type_name` strings differ by driver (this is where cross-driver bugs hide).
5. Update the exception message that mentions installing `doctrine/dbal`.
6. Remove the `doctrine/dbal` requirement (Phase 2).

### 4.2 `admin:permissions` driver independence
Replace `DB::select('SHOW TABLES')` with `Schema::getTables()` (native). Test on SQLite and MySQL.

### 4.3 Dead compatibility code (A 3.7)
- `AdminServiceProvider::compatibleBlade()`: call `Blade::withoutDoubleEncoding()` directly; remove the reflection.
- `AdminServiceProvider::registerPublishing()`: publish `lang` via `lang_path()` only.
- `DefaultDatetimeFormat::serializeDate()`: drop the `< 7.0` branch.
- `Grid\Model::joinParameters()`: always `getForeignKeyName()`.

### 4.4 Request-forgery and session verification (A 3.5, risks R3/R4)
1. Add tests (or manual checks) with a logged-in admin for: POST/PUT/DELETE through the normal form, the PJAX flow, and inline editing / batch actions that send `_token`.
2. Test with `admin.route.domain` set and behind a reverse proxy (`X-Forwarded-*`), watching for `Sec-Fetch-Site` rejections.
3. Test flashed `MessageBag`/`ViewErrorBag` round-trip with `session.serialization = json` (Laravel 13 default). If it fails, flash arrays and rebuild the bag on read.
4. Update any docs/tests that mention `VerifyCsrfToken`; reference `PreventRequestForgery` for exclusions.

### 4.5 Routing
- Confirm the `Admin::routes()` and `Route::group(['namespace' => ...])` style still registers and resolves on 13.x (it did in the smoke test).
- Update the console stubs (`routes.stub`, `ExampleController.stub`, `HomeController.stub`, `controller.stub`) to the tuple syntax `[Controller::class, 'method']`; keep a `--legacy` note in docs if needed.
- Domain-route precedence: test with a configured domain (A 3.5).

### 4.6 Defensive fixes surfaced by the smoke test
- `UploadField::$storage` defaults to `''`; if `disk()` fails it stays a string and `destroy()` fatals (`Call to a member function exists() on string`). Guard with `if ($this->storage && ...)`, or throw a clear config exception early.
- `Pjax::handleErrorResponse` reads `$response->exception`; guard with `isset`.

### 4.7 Image handling (A 3.4), if migrating Intervention
1. Add `intervention/image` `^3|^4` and `intervention/image-laravel`; update `suggest`.
2. Rewrite `ImageField::uploadAndDeleteOriginalThumbnail()`:
   - `ImageManager`/facade `read($file)` instead of `make($file)`.
   - `resize/scale/cover` with new semantics (the existing `$size[2]` action name, such as `resize` or `fit`, maps differently; define a compatibility mapping and document it).
   - Canvas padding (`resizeCanvas(..., 'center', false, '#ffffff')`) becomes `contain()`/`resizeCanvas` equivalents in v3+.
   - `encode()` returns an encoded-image object in v3+; use `->toString()`.
3. Check `Traits/Resizable.php` and `Grid/Column/ExtendDisplay.php` references.
4. Tests: upload JPEG/PNG, with and without thumbnails, single and multiple, delete and replace. Existing `ImageUploadTest` (9 tests) covers much of this once the harness runs.

**Exit criteria:** full suite green on SQLite; generator green on MySQL; CSRF/session scenarios verified.

---

## Phase 5: Broader functional verification (2 to 3 days)

The smoke test covered the core CRUD pages. This phase covers what it did not (A section 1 caveat). Build a checklist and run it by test or by hand, on **MySQL 8 and PostgreSQL** as well as SQLite:

| Area | What to exercise |
|---|---|
| Grid | Filters (all types), scopes, quick search, column selector, sorting by relation (`joinParameters`), `Selectable`, `Simple` grid, header/footer, row actions, batch actions, per-page, export (CSV and Excel exporter), inline editing |
| Form | All field types in `src/Form/Field/` (including date/time, editors, map, listbox, tags, icon, color, embeds, `HasMany`, nested forms, tab/step forms, value picker, captcha), validation, hooks (`saving`/`saved`/`deleting`), file/image upload and delete |
| Show | Fields, relations, panels, `as()` callbacks |
| Tree | Menu drag-order, `ModelTree` branch save and order |
| Actions | Row/batch actions, `Interactor` form and dialog, `HandleController` routes (`_handle_action_`, `_handle_form_`, `_handle_selectable_`, `_handle_renderable_`) |
| Auth | Login, remember me, logout, user setting, permission middleware (path/method matching), role/menu visibility |
| Widgets | Box, Tab, Table, Alert, Chart, Collapse, Carousel, InfoBox, `Form` widget with `StepForm` |
| Console | `admin:install`, `admin:publish`, `admin:make`, `admin:controller`, `admin:form`, `admin:action`, `admin:menu`, `admin:permissions`, `admin:create-user`, `admin:reset-password`, `admin:export-seed`, `admin:generate-menu`, `admin:extend`, `admin:import`, `admin:uninstall` |
| Extension API | `Extension::` config, routes, menu and permission import (`DB::transaction` path) |
| Long-running servers | Octane/Swoole smoke test (global `exit`, static state, config mutation; A 3.7 and 3.9). If unsupported, document it |

Fix defects found; each gets a regression test.

**Exit criteria:** checklist complete, defects fixed or documented as known issues.

---

## Phase 6: CI, packaging and docs (1 day)

1. **GitHub Actions** replace `.travis.yml`:
   - Single PHP `8.5` x Laravel 13; matrix over DB only: `sqlite`, `mysql:8`, `postgres`.
   - `composer validate --strict`, install, `php -l` sweep, test suite with deprecations as failures.
   - Add `--prefer-lowest` leg to confirm the minimum constraints work.
2. Keep Dependabot, but reduce `daily` to `weekly` to cut noise.
3. **Docs:** update `README.md` requirements (PHP, Laravel), installation steps (the `admin` disk must be defined; the L12+ `local` disk root is `storage/app/private`), `docs/en/installation.md`, `docs/en/change-log.md`, `CHANGELOG.md`.
4. **Upgrade guide** (`UPGRADE.md`): breaking changes list (PHP 8.5 minimum, Laravel 13 only, DBAL removed, Intervention change, generator changes, CSRF/session notes).
5. Tag `3.0.0` (needs your approval).
6. Packagist: since this is a fork, publish under your own vendor name (the current `encore/laravel-admin` name belongs to the original author). Choose the package name and update `composer.json` `name`, `homepage` and `authors` before any release.

---

## Phase 7: Security and compliance hardening (2 to 4 days, can run in parallel with Phase 5)

These are not Laravel-13 blockers but were found in the analysis (A 3.9) and matter before using this in any payment-related environment (PCI-DSS, Bangladesh Bank requirements):

1. **Operation log redaction:** add a configurable list (`operation_log.redact`, default `password`, `password_confirmation`, `_token`, `current_password`, card/PAN-like keys); apply before `json_encode`. Add a retention/pruning command or documented guidance.
2. **Default credentials:** make `admin:install` generate or prompt for a password; force a password change on first login or document it loudly. Remove the hard-coded `admin`/`admin`.
3. **Password column:** new migration to widen `password` from 60 to 255.
4. **Front-end vendor assets:** inventory exact versions of AdminLTE, Bootstrap, jQuery, moment, sweetalert2, fileinput, datetimepicker etc.; check them against advisories; upgrade or isolate (CSP headers, no inline script where possible). A full UI framework replacement is a separate project; decide scope.
5. **Session/guard config:** document the global-config mutation and Octane caveat; consider scoping guard selection to the admin routes only.
6. **Data privacy:** document what personal data the admin stores (usernames, names, avatars, IPs in the operation log) so operators can map it to their data-protection obligations.
7. Add `SECURITY.md` and enable secret scanning on the fork.

---

## Phase 8: Release and follow-up (0.5 day)

1. Final run of the full matrix.
2. Release notes from `UPGRADE.md`.
3. Open issues for deferred items: removal of global `exit`/`Request` facade use in model events, namespacing global helpers, Octane support, front-end modernisation, Intervention abstraction.

---

## Effort summary

| Phase | Estimate (focused dev-days) |
|---|---|
| 0 Decisions | 0.5 |
| 1 Test harness | 2 to 3 |
| 2 Composer constraints | 0.5 |
| 3 PHP 8.4/8.5 cleanup | 1 |
| 4 Laravel API changes | 2 to 3 |
| 5 Functional verification | 2 to 3 |
| 6 CI, packaging, docs | 1 |
| 7 Security hardening | 2 to 4 |
| 8 Release | 0.5 |
| **Total** | **about 12 to 17 days** (core compatibility, Phases 0 to 6, is about 9 to 12) |

Estimates assume a developer familiar with Laravel and without AI assistance; the mechanical parts (Phase 3, doc updates) compress a lot with it, the verification and design parts (Phases 1, 4.1, 4.7, 5) do not.

## Rollback and safety

- Work on a branch; keep `master` untouched until Phase 5 passes.
- Keep the last Laravel 9 release tagged and branch `2.x` so existing users are not stranded.
- Each phase ends with a green (or documented) test run; do not start the next on a red build.

## Quick verification commands (devbox)

```bash
distrobox enter devbox -- composer validate --strict
distrobox enter devbox -- composer update --no-interaction
distrobox enter devbox -- bash -c "find src tests database config -name '*.php' -print0 | xargs -0 -n1 php -d error_reporting=-1 -l 2>&1 | grep -E 'Deprecated|Fatal|Parse'"
distrobox enter devbox -- vendor/bin/phpunit
```

---

## Implementation status (2026-10-26)

Scope applied: Laravel `^13.0`, PHP `^8.5`, Intervention Image `^4.0` with `intervention/image-laravel`. Nothing is committed; all changes are in the working tree.

Result (2026-10-27): `vendor/bin/phpunit` runs **110 tests, 1098 assertions, no failures, no deprecations** on PHP 8.5.11 / Laravel 13.38 with SQLite, and the same suite passes on **MySQL 8.4** (throwaway container, `MYSQL_PORT=33306`); random order is stable. See `OUTCOME.md` for the full test run and review. A `php -l` sweep over `src`, `tests`, `database` and `config` reports nothing; `composer validate --strict` passes.

| Phase | Status | Notes |
|---|---|---|
| 0 Decisions | Done | Laravel 13 + PHP 8.5 only; Intervention migrated to 4.x |
| 1 Test harness | Done | Testbench based `TestCase`, class factories, PHPUnit 13 configuration failing on deprecations/warnings/notices/risky, placeholders instead of credentials in `tests/config/filesystems.php` |
| 2 Composer | Done | `composer.json` rewritten; `maatwebsite/excel`, `intervention/image-laravel` added to `suggest` |
| 3 PHP 8.4/8.5 | Done | 37 implicit-nullable parameters, `Tree::$path`, null-to-`strlen`/`isValidUrl`, `HTML-ENTITIES` |
| 4 Laravel API | Done, one item open | DBAL removed (`ResourceGenerator` uses `Schema::getColumns()`), `admin:permissions` uses `Schema::getTables()`, dead version branches removed, Intervention 4.x, JSON session fix, upload/Pjax guards. **Open:** the console route stubs still use the `'Controller@method'` style (still supported by Laravel 13) |
| 5 Functional verification | Partial | New tests: console generators and permissions, PJAX, thumbnails, flash messages, login throttling, log redaction. MySQL 8.4 run done (all tests pass). **Not done:** PostgreSQL run, the CI workflow run (set aside), most form field types, tree/orderable grid, nested forms, Excel exporter, Octane |
| 6 CI, packaging, docs | Mostly done | `.github/workflows/tests.yml` (not executed yet), `.travis.yml` removed, Dependabot weekly, README, `docs/en/installation.md`, `UPGRADE.md`, `CHANGELOG.md`. **Open:** package name/vendor for the fork, version tag |
| 7 Security | Partly done | Done: SQL injection in the grid sort fixed, `_handle_*` routes restricted to forms/actions/selectables/renderables, script uploads refused (`upload.forbidden_extensions`), operation-log redaction (`operation_log.redact`), login throttling, `password` column `string(255)` for new installs, install-time reminder to change the default password. Front-end libraries inventoried, upgraded and audited (see `docs/frontend-dependency-audit.md`); grid displayers, toastr messages and the carousel id escaped. **Open:** forced password change / random initial password, Bootstrap 3 end-of-life advisory (needs Bootstrap 5), raw-HTML content widgets (`display`, `show` fields, boxes: by design), documentation of the data the admin stores |
| 8 Release | Not started | Review and remaining items: `OUTCOME.md` |

Verification notes for the reviewer:
- The package was also exercised in a throwaway Laravel 13 skeleton with a **file session and JSON serialization** (login, flash messages, failed login, user setting). That is how the session issue was found; the test suite alone would not have.
- Tests that touch the database drop all tables in `tearDown`, but only on `:memory:` or on a database whose name contains `test`.
- `tests/IndexTest` and `tests/AuthTest` no longer assert on the "dependencies" list: it is read from the host application's `composer.json`, which the Testbench skeleton does not provide.
- `tests/LaravelTest` was removed: it requested `/` and expected the Laravel welcome page, which a package test app does not have.
