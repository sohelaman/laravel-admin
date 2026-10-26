---
name: testing-workflow
description: Exact commands and gotchas for running the PHPUnit suite on SQLite and MySQL in this repo
metadata:
  type: project
---

Run with `XDEBUG_MODE=off` inside devbox: `distrobox enter devbox -- bash -c 'XDEBUG_MODE=off vendor/bin/phpunit --no-progress --colors=never'`.
MySQL: disposable container `docker run -d --name la-mysql-test -p 127.0.0.1:33306:3306 -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=laravel_admin_test mysql:8.4`, then `DB_CONNECTION=mysql MYSQL_HOST=127.0.0.1 MYSQL_PORT=33306 MYSQL_USER=root vendor/bin/phpunit`, then `docker rm -f la-mysql-test`. Never point the suite at the MySQL MCP/other databases: tests drop all tables.
Also run `--order-by=random` occasionally: order dependence bugs were found that way. Suite fails on any deprecation, so a green run = clean PHP 8.5.

**Why:** the suite is Testbench based with static `Admin` state and per-process temp directories (details in `CLAUDE.md` "Tests").
**How to apply:** before finishing any change run the suite and the `php -l` sweep; see [[user-working-rules]].
