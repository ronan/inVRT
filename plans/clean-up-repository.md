# Plan: Clean Up the Repository

## Objective

Reduce top-level clutter, consolidate all source code under `src/` by language, reorganise `docs/`, clean up `tooling/`, and move completed TODOs — without breaking any existing functionality or tests.

---

## Phase 1: Remove obsolete directories

- Remove `chats/` (old AI chat logs)
- Leave `scratch/` — it is gitignored and not committed
- Leave `simplerflow/` — it is a git worktree; the user will handle removing it

---

## Phase 2: Consolidate source code under `src/`

Target layout:
```
src/
  core/       ← PHP core library (currently core/src/)
  cli/        ← Symfony Console CLI (currently cli/)
  js/         ← Playwright & BackstopJS scripts (currently src/)
```

Steps:
1. Move `core/src/*.php` and `core/src/Service/` → `src/core/` (flatten the extra `src/` layer)
2. Move `cli/Commands/`, `cli/Input/`, `cli/invrt.php` → `src/cli/`
3. Move `src/backstop.js`, `src/playwright-*.js` → `src/js/`
4. Update `composer.json`:
   - `"App\\"` autoload path: `"cli/"` → `"src/cli/"`
   - `"InVRT\\Core\\"` autoload path: `"core/src/"` → `"src/core/"`
   - `"bin"`: `["cli/invrt.php"]` → `["src/cli/invrt.php"]`
5. Update `src/cli/invrt.php` require: `__DIR__ . '/../vendor/autoload.php'` → `__DIR__ . '/../../vendor/autoload.php'`
6. Slim `bin/invrt` to a thin wrapper: `require_once __DIR__ . '/../src/cli/invrt.php'`
7. Update `src/cli/Commands/BaseCommand.php` APP_DIR constant: `__DIR__ . '/../../src'` → `__DIR__ . '/../js'`
8. Update all tooling config relative paths:
   - `tooling/phpunit.xml`: `../core/src` → `../src/core`, `../cli` → `../src/cli`
   - `tooling/phpstan.neon`: `../core/src` → `../src/core`, `../cli` → `../src/cli`
   - `tooling/.php-cs-fixer.php`: same path updates
   - `tooling/rector.php`: same path updates
9. Update `Taskfile.yml` source globs: `core/src/**/*.php` → `src/core/**/*.php`, `cli/**/*.php` → `src/cli/**/*.php`
10. Update `tooling/.release-it.json` output: `"cli/invrt.php"` → `"src/cli/invrt.php"`
11. Run `composer dump-autoload` to regenerate the autoloader
12. Update `tests/e2e/CommandTestCase.php` if it contains any hardcoded paths to `cli/` or `core/`

---

## Phase 3: Reorganise `docs/`

### GitHub Pages note
`docs/CNAME` and `docs/index.html` must remain at the root of `docs/` — GitHub Pages (which serves `invrt.sh`) requires the CNAME and index to be at the root of the configured source folder. Moving them inside a subdirectory would take the site down. These two files stay in place.

Target layout:
```
docs/
  index.html        ← GitHub Pages entry (stays at docs/ root)
  CNAME             ← GitHub Pages custom domain (stays at docs/ root)
  user/en/          ← Human-readable end-user docs (.md only)
  developer/en/     ← Human-readable developer docs (.md only)
  spec/             ← Machine-readable specifications (YAML, JSON schema, etc.)
  planning/         ← Planning and specification documents (.md)
    agent-plans/    ← Agent-generated implementation plans (moved from plans/)
```

Moves:
- `docs/usage.md` → `docs/user/en/usage.md`
- `docs/configuration.md` → `docs/user/en/configuration.md`
- `docs/CODING_STANDARDS.md` → `docs/developer/en/CODING_STANDARDS.md`
- `docs/development.md` → `docs/developer/en/development.md`
- `docs/APP_SUMMARY.md` → `docs/developer/en/APP_SUMMARY.md`
- `docs/config.schema.yaml` → `docs/spec/config.schema.yaml`
- `docs/config.schema.tpl.yaml` → `docs/spec/config.schema.tpl.yaml`
- `docs/config.example.yaml` → `docs/spec/config.example.yaml`
- `docs/SITE_TREE_FILE_SPEC.md` → `docs/spec/SITE_TREE_FILE_SPEC.md`
- `docs/logos.txt` → `docs/website/logos.txt` (website asset, not a doc)
- `plans/*.md` → `docs/planning/agent-plans/*.md`

Create `docs/README.md` that shows the directory layout and links to `docs/user/en/usage.md`.

Update references after moves:
- `tooling/scripts/validate-schema.mjs`: update paths to `docs/spec/config.schema.yaml` and `docs/spec/config.example.yaml`
- `tooling/scripts/build-config-definition.mjs`: update schema path to `docs/spec/config.schema.yaml`
- `Taskfile.yml` `build:templates` sources: update to `docs/spec/config.schema.yaml`
- `AGENTS.md`: update plans directory reference to `docs/planning/agent-plans`
- `AGENTS.md`: update `docs/CODING_STANDARDS.md` reference to `docs/developer/en/CODING_STANDARDS.md`
- `AGENTS.md`: update `docs/APP_SUMMARY.md` reference to `docs/developer/en/APP_SUMMARY.md`
- `AGENTS.md`: update `docs/usage.md` and `docs/configuration.md` references
- `.agents/skills/SKILL.md`: update plans directory to `docs/planning/agent-plans`
- Scan all `.md` files for broken internal links and update them

---

## Phase 4: Clean up `tooling/`

1. Move config files into `tooling/config/`:
   - `.php-cs-fixer.php` → `tooling/config/.php-cs-fixer.php`
   - `phpunit.xml` → `tooling/config/phpunit.xml`
   - `phpstan.neon` → `tooling/config/phpstan.neon`
   - `phpstan-baseline.neon` → `tooling/config/phpstan-baseline.neon`
   - `rector.php` → `tooling/config/rector.php`
   - `.release-it.json` → `tooling/config/.release-it.json`
2. Remove unused files: `infection.json`, `phpmd.xml`, `phpmd-report.xml`, `10.5.xsd`
3. Update all `Taskfile.yml` config path references from `tooling/X` to `tooling/config/X`
4. Update the `includes` path in `tooling/config/phpstan.neon` for the baseline file
5. Update `.gitignore` cache file paths to reflect moved locations (e.g. `tooling/.php-cs-fixer.cache` → `tooling/config/.php-cs-fixer.cache`)

---

## Phase 5: Move completed TODOs

- Extract all `- [x]` items from `TODO.md` with their section headers
- Write them to `docs/planning/TODO-DONE.md`
- Remove them from `TODO.md`, keeping only open (`- [ ]`) and in-progress (`- [.]`) items

---

## Phase 6: Update agent instructions for TODO handling

- Add instructions to `AGENTS.md` and `TODO.md` so agents move completed items to `docs/planning/TODO-DONE.md` rather than only checking them off

---

## Phase 7: Miscellaneous cleanup

- Update `.dockerignore`: remove the `plans` entry (moved); remove `chats` entry (deleted)
- Review and update `README.md` links that reference moved docs

---

## Phase 8: Verify

- Run `task test` and confirm all tests pass
- Check the `bin/invrt` entry point works: `php bin/invrt list`
- Verify `task build:templates` and `task test:schema` work with new doc paths
- Move the "Clean up the repository" item from `TODO.md` to `docs/planning/TODO-DONE.md`
