# inVRT PHP Coding Standards

This guide outlines the code quality standards and build/test toolchain for the inVRT PHP/Symfony codebase.

## Tools Overview

| Tool | Purpose | Config File | Command |
|------|---------|-------------|---------|
| PHP CS Fixer | Auto-format code to PSR-12 | `.php-cs-fixer.dist.php` | `task fix:lint` |
| PHPStan | Static type analysis | `phpstan.neon` | `task test:phpstan` |
| Rector | Code modernization | `rector.php` | `task fix:modernize` |
| Composer Audit | Security scanning | (native) | `task security` |

## Local Development

### Before committing:
```bash
# Auto-fix style issues
task fix:lint

# Run all checks
task check

# Fix type hints and modernize code
task fix:modernize
```

### Before pushing:
```bash
# Run full test suite
task test
```

## CI/CD Integration

All checks run automatically:
1. **Pre-commit hook** — Runs on every commit locally
2. **CI pipeline** — Runs full suite including mutation testing

## Enforced Standards

- **PSR-12** — PHP coding standard (auto-enforced)
- **Type safety** — PHPStan Level 5 (no violations)
- **Security** — Composer audit passes

## Fixing Issues

### Style issues (auto-fixable):
```bash
task fix:lint
```

### Type hints (auto-fixable):
```bash
task fix:modernize
```

### Security issues:
```bash
composer update [package]  # Update vulnerable dependency
composer audit  # Verify fix
```