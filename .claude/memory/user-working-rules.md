---
name: user-working-rules
description: Maintainer's standing rules for agents in this repo (devbox, no commits, no installs, status line, org rules)
metadata:
  type: feedback
---

- Run every PHP/Composer/Node command through `distrobox enter devbox -- ...`; the host has none of them.
- Never create branches or commits unless explicitly asked; leave files modified for review.
- Never install system packages or global tools; tell the user what is needed. Do not kill processes you did not start (a `pkill` was denied by the harness).
- End responses with the quick-recap status line (`🟢` done / `🟡` follow-up remains / `🔴` blocked).
- Org rules (SSLWIRELESS): never output real card numbers/tokens/credentials (use `[PLACEHOLDER]`), consider Bangladesh Bank compliance, prefer security-first choices, flag privacy concerns proactively.
- The user prefers a decision question only when scope truly forks (e.g. frontend upgrade depth was asked once; they chose "patch in place").

**Why:** stated in the user's global CLAUDE.md and during the upgrade sessions.
**How to apply:** follow before every action; see [[project-overview]] and [[testing-workflow]].
