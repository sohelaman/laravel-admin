# Front-end dependency audit and upgrade

Date: 2026-10-26. Scope decided with the maintainer: **patch in place** (keep Bootstrap 3 and AdminLTE 2, move every library to the newest release that works with them).

## 1. Findings before the upgrade

`npm audit` on the exact versions that were bundled (versions that npm no longer serves were replaced by the closest release):

| Library | Bundled | Advisories |
| --- | --- | --- |
| jQuery | 2.1.4 | XSS (several, fixed in 3.4.0 and 3.5.0) |
| Bootstrap | 3.3.4 js / 3.3.5 css | XSS in tooltip/popover/collapse (fixed in 3.4.0), carousel and data-attribute XSS (no fix for 3.x) |
| select2 | 4.0.3 | XSS (fixed in 4.0.6) |
| moment | 2.10.6 | ReDoS (fixed in 2.19.3), path traversal in `moment.locale` (fixed in 2.29.2) |
| eonasdan datetimepicker | 4.17.37 | inherits the bootstrap and moment-timezone advisories |
| AdminLTE | 2.3.2 | bundles the Bootstrap above; its npm package pulls chart.js and morris.js (prototype pollution, XSS), which this package never shipped |

Libraries without a published advisory but unmaintained or very old: jquery.nestable (2012), iCheck 1.0.1, inputmask 3.2.8, ion.rangeSlider 1.8, bootstrap-fileinput 4.5.2 (no HTML sanitizer), sweetalert2 7.x, toastr 2.1.3, fontawesome-iconpicker 1.x.

## 2. Result

`npm audit` of the shipped set now reports **one** moderate advisory: Bootstrap 3.4.1.

Bootstrap 3 reached end of life, so no patched 3.x release exists. The advisories concern content that reaches the tooltip, popover and carousel components through `data-*` attributes. What was done about it in this package:

- the carousel widget writes the element id with `{{ }}` (it was `{!! !!}`);
- the QR code and copyable grid displayers (the only `data-content` / popover users with data of the database) escape what they write (see 4.3);
- nothing else in the package builds tooltip or popover content from user data.

Removing the advisory needs Bootstrap 5 (AdminLTE 4), which rewrites the markup of every view and breaks the extensions; that is the "full modernization" option that was not chosen. Applications that need it should plan it as a separate major release.

Newer majors that were not taken because of the Bootstrap 3 base: jQuery 4.0.0 (the Bootstrap 3 scripts refuse to load on jQuery 4), Bootstrap 5.3.8, AdminLTE 4.10.0, bootstrap-colorpicker 3.x (Bootstrap 4+), Font Awesome 5/6/7 (the icon class names of the whole package are Font Awesome 4).

## 3. Versions

See `resources/assets/VERSIONS.md` for the full list. Main changes: jQuery 2.1.4 to 3.7.1, Bootstrap 3.3.x to 3.4.1, AdminLTE 2.3.2 to 2.4.18, select2 4.0.3 to 4.1.0, moment 2.10.6 to 2.31.0, sweetalert2 7.26 to 11.26, bootstrap-fileinput 4.5.2 to 5.5.4, inputmask 3.2.8 to 5.0.10, ion.rangeSlider 1.8 to 2.6, iCheck 1.0.1 to 1.0.2, toastr 2.1.3 to 2.1.4, nestable to nestable2 1.6.0, fontawesome-iconpicker to 3.2.0, jquery-pjax to 2.0.1, bootstrap-switch 3.3.2 to 3.4.0, datetimepicker 4.17.37 to 4.17.49, duallistbox 3.0.6 to 3.0.9. Not changed because they are already the latest: flatpickr 4.6.13, x-editable 1.5.1, Font Awesome 4.7, slimScroll 1.3.8, nprogress 0.2.0.

`bootstrap-slider` (AdminLTE plugin, 2.0) was removed, nothing used it.

## 4. Code changes needed by the new libraries

1. **jQuery 3**: `$.isArray` and `.unbind()` replaced (`Array.isArray`, `.off()`). The package did not use the jQuery 3 removals (`.load()`, `.error()` and `.success()` on requests, `.size()`).
2. **AdminLTE 2.4**: the header toggle is `data-toggle="push-menu"` and the sidebar menu has `data-widget="tree"`; `dist/js/app.min.js` is now `adminlte.min.js`. The menu activation code of `laravel-admin.js` was checked with the new tree widget.
3. **sweetalert2 11**: the `swal` global no longer exists. All calls use `$.admin.swal`, a wrapper of `Swal.fire` that still accepts the old `type` option (renamed `icon`); `window.swal` is kept for extensions.
4. **bootstrap-fileinput 5**: the `gly` theme is loaded and selected (`theme` option) so the Bootstrap 3 icons are used, `piexif`, `sortable` and DOMPurify (preview sanitizer) are loaded before the plugin, `canvas-to-blob` is gone, caption css adjusted.
5. **inputmask 5**: file renamed (`jquery.inputmask.min.js`); the aliases and options used by the package (`ip`, `email`, `url`, `integer`, `decimal`, `currency`, `mac`, `percentage`, masks) exist unchanged.
6. **ion.rangeSlider 2**: the slider field uses `prettify_enabled`, `grid` and `skin`; the old `prettify` and `hasGrid` options are translated.
7. **fontawesome-iconpicker 3**: it ships the Font Awesome 5 icon list, so `fa4-icons.js` provides the 4.7 list and class format (without it every value of the form field is flagged as invalid).
8. **bootstrap-colorpicker**: it triggers a jQuery event named `showPicker`, which jQuery also calls as the native `HTMLInputElement.showPicker()` of recent browsers (an exception on every open); `laravel-admin.js` neutralizes the native call.
9. **nestable2**: drop-in replacement, `nestable('serialize')` and the change events behave as before.

## 5. Security fixes found while testing the UI

- Grid displayers wrote the raw value of the row into the page (stored XSS for `label`, `badge`, `link`, `button`, `prefix`, `suffix`, `limit`, `secret`, `copyable`, `qrcode`, `downloadable`, `image`). They escape it now (`e()`, so an `HtmlString` keeps working), and `link` no longer accepts `javascript:`, `vbscript:` and `data:` urls. Regression tests: `tests/GridDisplayerTest.php`.
- The toastr script of the flash messages put the message inside a JS string without encoding (`'...'`); it uses `@json` now and the type is checked against a list.
- The sidebar search built a regular expression from the typed text; it is escaped.
- The carousel widget element id is escaped.

## 6. How it was verified

- `vendor/bin/phpunit`: 110 tests at the time of the final run, see `OUTCOME.md` (new: `AssetsTest` checks that every asset referenced by the sources, views and config exists and that the old library versions are gone; `GridDisplayerTest`; toastr tests in `FlashMessageTest`).
- In Firefox against a Laravel 13 application with a page of ~50 form fields, a grid and the menu tree: no JavaScript error or rejection (a probe collected `error`, `unhandledrejection`, `console.error` and `console.warn`), select2, datetimepicker, iconpicker, colorpicker, switch, iCheck, input masks, dual listbox, file input previews (single, multiple), sortable, x-editable popover, dropdown actions, sweetalert2 confirm/cancel/success (grid row delete, tree delete), toastr, nestable, sidebar tree, push-menu toggle and PJAX navigation work; screenshots of the form before and after were compared.
- jQuery Migrate 3.6 reported only calls that matter for jQuery 4 (event shorthands, `$.isFunction`, `$.trim`, ...), made by the plugins; nothing that is removed in 3.x.

Not verified: Internet Explorer and old browsers (jQuery 3 and select2 4.1 do not support them), slider dragging, flatpickr (no field of the package uses it by default), date/time pickers in a locale other than English, the `admin:minify` command with the new files.

## 7. Differences users will see

- Currency masks do not show `0.00` until the field gets the focus; the slider uses the `round` skin; colour fields start empty instead of black; the select2 multiple choices show the remove button before the text; file inputs use the 5.x layout.
- Applications that published the assets must publish them again (`php artisan admin:publish --force`).
