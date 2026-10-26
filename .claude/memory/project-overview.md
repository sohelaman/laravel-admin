---
name: project-overview
description: What this repo is (fork of encore/laravel-admin 1.8.19 revived for Laravel 13 / PHP 8.5), owner context and current state
metadata:
  type: project
---

Fork of the unmaintained `encore/laravel-admin` (origin `sohelaman/laravel-admin`, branch `revive`, base tag v1.8.19), owned by SSLWIRELESS (PCI-DSS payment gateway, Bangladesh). Goal: support **Laravel 13 + PHP 8.5 only** with safe front-end libraries (`NOTES.md`).

As of 2026-10-27 the upgrade is complete but **uncommitted** in the working tree: 111 tests green on SQLite and MySQL 8.4; PostgreSQL and CI never run. A security review fixed SQL injection (grid sort), arbitrary class instantiation (`_handle_*` routes), script uploads, stored XSS in grid displayers, JSON-session flash crash.

**Why:** the maintainer wants to use the fork in projects that were built on 1.8.19 (see [[migration-guide-for-users]]).
**How to apply:** read `CLAUDE.md` and `OUTCOME.md` first; treat the working tree as pending review, never commit on your own ([[user-working-rules]]).
