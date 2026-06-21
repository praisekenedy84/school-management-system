---
description: Run the full pre-merge gate before marking a task done
allowed-tools: Bash, Read, Grep, Glob
---

Run the project's definition-of-done gate. Report a clear PASS/FAIL for each, then an overall verdict.

1. Code style:
   !`./vendor/bin/pint --test 2>&1 | tail -20`

2. Full test suite:
   !`php artisan test 2>&1 | tail -30`

3. Tenant isolation specifically:
   !`php artisan test --filter=Tenant 2>&1 | tail -20`

4. Docs hygiene (read, don't run):
   - Is `CHANGELOG.md` Unreleased section updated for this change?
   - Is the relevant `PROJECT-PLAN.md` task checked off?
   - If an architectural decision was made, is the `ARCHITECTURE.md` ADR log updated?

5. If anything touched tenancy, auth, uploads, or finance, confirm a **security-auditor** pass happened
   with no open CRITICAL/HIGH findings.

Overall verdict: **SHIP** only if all of the above pass; otherwise list exactly what's blocking.
