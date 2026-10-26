---
name: migration-guide-for-users
description: Pointer to the user-facing migration guide and the facts it encodes (composer alias, publish tags, breaking changes)
metadata:
  type: reference
---

`MIGRATING.md` (repo root) tells a project on 1.8.19 how to switch: VCS/path repository with alias `dev-revive as 1.8.99` (official extensions need `~1.x`), remove doctrine/dbal, Intervention 2 -> 4 (+ image-laravel), `vendor:publish --tag=laravel-admin-assets --force` (never `admin:publish --force`, it overwrites config/lang/migrations), new config keys `upload.forbidden_extensions` and `operation_log.redact`, flash messages are arrays, chained grid displayers escape (use `HtmlString`). Not yet exercised on a real 1.8.19 project.
Related: `UPGRADE.md` (behaviour changes), `OUTCOME.md` (status and open items), [[project-overview]].
