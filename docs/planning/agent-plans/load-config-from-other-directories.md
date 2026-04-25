# Load config from other directories

## Goals

The TODO item has two parts:

1. **Use `plan.yaml` as the configuration file.** Stop using `config.yaml`
   entirely. The `project`, `environments`, `profiles`, and `devices`
   sections currently written to `.invrt/config.yaml` move into
   `plan.yaml`. `invrt init` writes defaults straight to `plan.yaml`; the
   config reader reads from `plan.yaml`. `Configuration` reads/writes
   through `PlanService` rather than parsing YAML itself. Schema validation
   (and the `symfony/config` dependency it requires) is removed.
2. **Search multiple directories for `plan.yaml`.** When discovering the
   inVRT directory the loader looks (in order) at:
   - `<cwd>/invrt/plan.yaml`
   - `<cwd>/.invrt/plan.yaml`
   - `<cwd>/.ddev/.invrt/plan.yaml`
   - `<cwd>/.ddev/invrt/plan.yaml`

No backward compatibility with `config.yaml` is required.

## Non-goals

- Renaming the `invrt` / `.invrt` directory in already-initialized projects
  (covered by the future `--hide` / `--unhide` TODOs).
- Changing the `pages:` tree shape in `plan.yaml` or the way the crawler
  populates it.

## Affected layout

```
<cwd>/
  .invrt/                    ← still the default for new projects
    plan.yaml                ← project + environments + profiles + devices + exclude + pages
    data/...
    scripts/...
```

`config.yaml` is no longer written and no longer read anywhere in the
codebase.

## `plan.yaml` shape after the change

```yaml
project:
  url: https://example.com
  id: <id>
  name: My Project

environments:
  local:
    url: https://example.com

profiles:
  anonymous: {}

devices:
  desktop: {}

exclude:
  - /logout
  - /user/logout

pages:
  /: {}
```

The first four blocks are what currently live in `config.yaml`. `exclude`
and `pages` already live in `plan.yaml` and stay where they are.

`PlanService::orderTopLevel()` is updated so the merged keys come out in a
stable, readable order:
`project`, `environments`, `profiles`, `devices`, `exclude`, `pages`.

## Implementation

### 1. `Configuration` reads/writes via `PlanService`

`InVRT\Core\Configuration` already merges
`project` / `environments` / `profiles` / `devices`. Refactor it so:

- Parsing the YAML file is delegated to `PlanService::read()` (made
  `public` for this purpose) instead of `YamlLoader::fromFile()`.
- `Configuration::write()` delegates to `PlanService` (a small new public
  writer that takes an array) instead of writing YAML directly.
- The `getWarnings()` mechanism stays on `Configuration` but always
  returns `[]` (kept so `BaseCommand` need not change). It can be
  removed in a follow-up.

### 2. Remove schema validation and `symfony/config`

- Delete `src/core/YamlLoader.php`.
- Strip `ConfigurationInterface` / `getConfigTreeBuilder()` / `TreeBuilder`
  out of `src/core/ConfigSchema.php`. Keep only the `DEFAULTS` constant.
  Update `tooling/templates/ConfigSchema.tpl.php` to match.
- Drop `symfony/config` from `composer.json`. Run `composer update` to
  refresh `composer.lock`.
- No tests currently depend on schema-validation warnings; no test
  changes needed for this step beyond §9.

### 3. Locate `plan.yaml` in `BaseCommand::resolveConfigFilepath()`

Replace the body with a search in this order:

1. Explicit override: `INVRT_PLAN_FILE`, then `INVRT_DIRECTORY` (joined
   with `plan.yaml`).
2. First existing `plan.yaml` under
   `<cwd>/{invrt,.invrt,.ddev/.invrt,.ddev/invrt}`.
3. Fallback (used by `init` when nothing exists yet):
   `<cwd>/.invrt/plan.yaml`.

The fallback preserves the current default behavior of `invrt init` —
running `init` in an empty directory creates `<cwd>/.invrt/plan.yaml`.

The candidate directory list lives as a constant on `BaseCommand` so
future commands and tests can reuse it.

### 4. Remove `INVRT_CONFIG_FILE`

`INVRT_PLAN_FILE` becomes the only env var pointing at the plan file.

- Drop `INVRT_CONFIG_FILE` from `ConfigSchema::DEFAULTS`.
- Update every reference in `src/`, `tests/`, and `docs/user/`:
  - `src/cli/Commands/BaseCommand.php` (debug line, override check)
  - `src/cli/Commands/InfoCommand.php` (display)
  - `src/core/Configuration.php` (export at end of `resolve()`)
  - `src/js/info.js` (`INVRT_CONFIG_FILE` → `INVRT_PLAN_FILE`)
  - `tests/bats/*.bats`, `tests/bats/test_helper.bash`
  - `docs/user/en/*.md`
- Anywhere user docs say "config file", "config.yaml", or
  "`.invrt/config.yaml`", replace with "plan file" / `plan.yaml`.

### 5. `INVRT_DIRECTORY` derived from the discovered file

Today `INVRT_DIRECTORY` defaults to `INVRT_CWD/.invrt`. After the change:

- If a `plan.yaml` was discovered, set `INVRT_DIRECTORY` to its parent
  dir so `data_dir`, `scripts_dir`, etc. resolve next to the discovered
  plan.
- If no plan was discovered, fall back to `INVRT_CWD/.invrt`.

This is set in `BaseCommand::boot()` by adding `INVRT_DIRECTORY` to the
env array passed to `Configuration` whenever the resolved filepath is
non-default.

### 6. `Runner::init()` writes only `plan.yaml`

`Runner::init()` currently writes both `config.yaml` and `plan.yaml`.
After the change:

- Drop the `Yaml::dump([... 'project', 'environments', 'profiles',
  'devices' ...])` / `Filesystem::writeFile($configFile, …)` block.
- Extend the `PlanService::update()` call so the same data lands in
  `plan.yaml`:
  - `environments` → `[$environment => ['url' => $url]]`
  - `profiles`     → `[$profile => []]` (currently a flat list — see below)
  - `devices`      → `[$device => []]`
- Stop using `INVRT_CONFIG_FILE` (it no longer exists).

`PlanService::update()` currently treats `profiles` as a flat string list
(used by `check()` to record which profiles exist). Extend it so:

- `profiles` accepts either a flat list or a keyed map. When a flat list
  is passed and the existing `profiles` block is missing or empty, the
  list is normalised into a map (`['anonymous' => []]`); when a keyed
  map already exists it is preserved.
- New `environments` and `devices` keys accept keyed maps and merge into
  the plan without clobbering existing entries.

The `check()` call site keeps passing a flat list (we only update names
there); `init()` passes maps.

### 7. `ConfigSchema::DEFAULTS` cleanup

- Remove `config_file`.
- `plan_file` becomes the canonical key (`INVRT_DIRECTORY/plan.yaml`).
- Every other default keeps its current value.
- Regenerate the templated `ConfigSchema.php` via `task build:templates`
  after updating the spec source (`docs/spec/config.schema.yaml`) and
  the template.

### 8. Docs

- `docs/spec/config.schema.yaml`: remove `config_file`, describe the
  configuration file as `plan.yaml`, document the search order.
- `docs/developer/en/APP_SUMMARY.md`: describe the new search order and
  the merged `plan.yaml` (no `config.yaml`).
- `docs/user/en/usage.md` and `docs/user/en/configuration.md`: replace
  every `config.yaml` reference with `plan.yaml`; document the search
  paths.

### 9. Tests

- Update `tests/bats/test_helper.bash` and any `.bats` cases that
  reference `config.yaml` / `INVRT_CONFIG_FILE` to use `plan.yaml` /
  `INVRT_PLAN_FILE`.
- Rename `tests/fixtures/config.yaml` → `tests/fixtures/plan.yaml` and
  `tests/fixtures/config-minimal.yaml` → `tests/fixtures/plan-minimal.yaml`.
  Update fixture contents to a valid plan shape (top-level
  `project`, `environments`, etc., plus a `pages:` block).
- New Bats coverage:
  - `invrt init` produces `<cwd>/.invrt/plan.yaml` containing
    `project` / `environments` / `profiles` / `devices` / `exclude` /
    `pages`, and produces **no** `config.yaml`.
  - Running a command from a project where `plan.yaml` lives in
    `invrt/`, `.ddev/.invrt/`, and `.ddev/invrt/` finds the file (one
    case per directory).
  - `INVRT_PLAN_FILE` override still wins.

## Removal checklist

After implementation, verify with `grep`:

- No references to `config.yaml` remain in `src/`, `tests/`, or
  `docs/user/`.
- No references to `INVRT_CONFIG_FILE` remain anywhere.
- No references to `symfony/config`, `YamlLoader`, `ConfigSchema`'s
  validation tree, or `Symfony\Component\Config\…\Processor` remain.

## Out of scope / follow-ups

- The future TODOs `init --force`, `init --hide`, `init --unhide`, and
  `invrt remove` will reuse the same multi-directory search list.

## Review checklist

- [ ] Plan reviewed and approved
- [ ] `task build:templates` regenerates `ConfigSchema.php` cleanly
- [ ] `composer update` regenerates the lock file without `symfony/config`
- [ ] `task test` passes (Bats + lint)
- [ ] `invrt init` followed by `invrt reference` / `invrt test` works
      against `tests/fixtures/website/`
