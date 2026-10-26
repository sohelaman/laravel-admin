---
name: frontend-asset-workflow
description: How front-end libraries were audited/upgraded and how to verify UI changes (npm in devbox, scratch Laravel app, Firefox MCP)
metadata:
  type: project
---

Libraries are committed in `resources/assets` (inventory `resources/assets/VERSIONS.md`). Decision: stay on Bootstrap 3.4.1 + AdminLTE 2.4.18 + jQuery 3.7.1 (user chose "patch in place"); Bootstrap 5/AdminLTE 4/jQuery 4 would be a rewrite. Only remaining `npm audit` advisory: Bootstrap 3 (EOL).
Process: npm install the packages in a scratch dir (devbox), copy dist files, fix PHP path constants (`Traits/HasAssets.php`, `Form/Field/*`), adapt JS, run `tests/AssetsTest.php`.
UI verification used a scratch Laravel 13 app with the package symlinked, a demo page with ~50 fields, and the Firefox DevTools MCP (Chrome is not installed; screenshots must be saved under `/home/sohel/.firefox-devtools-mcp/output`). An error probe script collected `window.__errs`.

**Why:** the scratch setup lived in the session scratchpad and is gone; recreate it when UI work is needed.
**How to apply:** see `docs/frontend-dependency-audit.md` and [[project-overview]].
