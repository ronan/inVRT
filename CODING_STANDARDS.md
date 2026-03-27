# inVRT PHP Coding Standards

Standards enforced in this project:

- **PSR-12** — auto-enforced by PHP CS Fixer (`.php-cs-fixer.dist.php`)
- **Type safety** — PHPStan Level 5 (`phpstan.neon`)
- **Code modernization** — Rector (`rector.php`)
- **Security** — Composer Audit

For all task commands, see `.github/copilot-instructions.md`.

## Workflow

**Before committing:** `task fix` then `task check`

**Before pushing:** `task test`

## Fixing Issues

**Style:** `task fix:lint`

**Types / modernization:** `task fix:modernize`

**Security:**
```bash
composer update [package]
composer audit
```