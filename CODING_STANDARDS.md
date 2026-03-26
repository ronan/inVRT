# inVRT PHP Coding Standards

This guide outlines the code quality standards and build/test toolchain for the inVRT PHP/Symfony codebase.

## Tools Overview

| Tool | Purpose | Config File | Command |
|------|---------|-------------|---------|
| PHP CS Fixer | Auto-format code to PSR-12 | `.php-cs-fixer.dist.php` | `task lint:fix` |
| PHPStan | Static type analysis | `phpstan.neon` | `task analyze` |
| Rector | Code modernization | `rector.php` | `task analyze:fix` |
| Composer Audit | Security scanning | (native) | `task security` |

## Local Development

### Before committing:
```bash
# Auto-fix style issues
task lint:fix

# Run all checks
task check

# Fix type hints and modernize code
task analyze:fix
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
task lint:fix
```

### Type hints (auto-fixable):
```bash
task analyze:fix
task analyze:modernize  # If safe
```

### Security issues:
```bash
composer update [package]  # Update vulnerable dependency
composer audit  # Verify fix
```