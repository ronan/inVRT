# inVRT PHP Coding Standards

Standards enforced in this project:

- **PSR-12** — auto-enforced by PHP CS Fixer (`.php-cs-fixer.dist.php`)
- **Security** — Composer/npm Audit

For all task commands, see `AGENTS.md`.

## Workflow

**Before committing:** `task fix` then `task test`

## Fixing Issues

**Style & Types:** `task fix`

**Security:**

Run package manager audits to check for vulnerable dependencies.

```bash
task test:security
```
