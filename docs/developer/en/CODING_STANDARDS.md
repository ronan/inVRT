# inVRT Coding Standards

Standards enforced in this project:

- **Type safety** — keep the TypeScript CLI passing `npm run typecheck`
- **Security** — npm Audit

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
