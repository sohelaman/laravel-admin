# Bundled front-end libraries

The files in this directory are copied from the npm packages below (the `dist` / `build` files only), without modification, except where a note says otherwise. Update them together with `UPGRADE.md` and run `vendor/bin/phpunit` (`tests/AssetsTest.php` checks that every asset referenced by the package exists).

| Directory | npm package | Version | Notes |
| --- | --- | --- | --- |
| `AdminLTE/plugins/jQuery` | `jquery` | 3.7.1 | latest 3.x, the Bootstrap 3 scripts do not load on jQuery 4 |
| `AdminLTE/bootstrap` | `bootstrap` | 3.4.1 | last 3.x release, end of life (see the audit) |
| `AdminLTE/dist` | `admin-lte` | 2.4.18 | last 2.x release; `dist/img/user2-160x160.jpg` is kept from 2.3 (default avatar) |
| `AdminLTE/plugins/select2` | `select2` | 4.1.0 | |
| `AdminLTE/plugins/iCheck` | `icheck` | 1.0.2 | |
| `AdminLTE/plugins/colorpicker` | `bootstrap-colorpicker` | 2.5.x | 3.x needs Bootstrap 4; image urls of the css point to `img/` |
| `AdminLTE/plugins/input-mask` | `inputmask` | 5.0.10 | `dist/jquery.inputmask.min.js` |
| `AdminLTE/plugins/ionslider` | `ion-rangeslider` | 2.6.0 | |
| `AdminLTE/plugins/slimScroll` | `jquery-slimscroll` | 1.3.8 | |
| `bootstrap-fileinput` | `bootstrap-fileinput` | 5.5.4 | with the `gly` theme (glyphicons of Bootstrap 3); `purify.min.js` is `dompurify` 3.4.17 |
| `bootstrap-switch` | `bootstrap-switch` | 3.4.0 | |
| `eonasdan-bootstrap-datetimepicker` | `eonasdan-bootstrap-datetimepicker` | 4.17.49 | |
| `bootstrap-duallistbox` | `bootstrap-duallistbox` | 3.0.9 | |
| `bootstrap3-editable` | `x-editable` | 1.5.1 | `dist/bootstrap3-editable`, no newer release |
| `flatpickr` | `flatpickr`, `shortcut-buttons-flatpickr` | 4.6.13, 0.4.0 | |
| `fontawesome-iconpicker` | `fontawesome-iconpicker` | 3.2.0 | `fa4-icons.js` (generated from Font Awesome 4.7) replaces the Font Awesome 5 icon list |
| `font-awesome` | `font-awesome` | 4.7.0 | latest 4.x |
| `jquery-pjax` | `jquery-pjax` | 2.0.1 | |
| `moment` | `moment` | 2.31.0 | `moment-with-locales.min.js` |
| `nestable` | `nestable2` | 1.6.0 | replaces `jquery.nestable` (2012) |
| `nprogress` | `nprogress` | 0.2.0 | |
| `sweetalert2` | `sweetalert2` | 11.26.25 | `window.swal` is a compatibility wrapper (`laravel-admin.js`) |
| `toastr` | `toastr` | 2.1.4 | |
| `number-input`, `google-fonts`, `laravel-admin` | - | - | maintained in this repository |

To check the libraries for known vulnerabilities, install the packages of the table in an empty directory and run `npm audit`.
