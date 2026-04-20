# Plan: Rename `settings` → `project`, Move `name` Inside

## Goal

- Replace the top-level `settings:` YAML section with `project:`
- Move the top-level `name:` key into `project.name:`
- The `project` section is semantically "base defaults for this project" (same functional role as `settings`)
- `name` remains a display label only — it does not become an env var (`INVRT_NAME`)
- `id` stays inside the section (now `project.id`) and continues to become `INVRT_ID`

## Before / After Example

**Before:**
```yaml
name: My inVRT Project

settings:
  id: xkqjmxvte
  url: https://example.com
```

**After:**
```yaml
project:
  name: My inVRT Project
  id: xkqjmxvte
  url: https://example.com
```

---

## Files to Change

### 1. `docs/spec/config.schema.yaml`
- Remove top-level `name` property
- Rename `settings:` → `project:`
- Add `name` as a property inside the `project` object (alongside `configKeys`)

### 2. `docs/spec/config.example.yaml`
- Remove top-level `name:` line
- Rename `settings:` → `project:`
- Add `name: My inVRT Project` as first entry inside `project:`

### 3. `tooling/templates/ConfigSchema.tpl.php`
- Remove `->scalarNode('name')->defaultNull()->end()` from root level
- Rename `->arrayNode('settings')` → `->arrayNode('project')`
- Add `->scalarNode('name')->defaultNull()->end()` as the first child inside the `project` array node

### 4. `src/core/ConfigSchema.php` (auto-generated — update directly too)
- Same structural changes as the template:
  - Remove top-level `name` scalar node
  - Rename `arrayNode('settings')` → `arrayNode('project')`
  - Add `name` scalar node as first child inside `project`

### 5. `src/core/Configuration.php`
- `resolve()`: change `$this->parsed['settings'] ?? []` → `$this->parsed['project'] ?? []`, then strip `name` key before calling `asEnv()` so it stays a display label
- `write()`: change `$data['settings'][$yamlKey]` → `$data['project'][$yamlKey]`

### 6. `src/core/Runner.php`
- `init()`: write `'project' => ['name' => ..., 'id' => ...]` instead of separate top-level `'name'` + `'settings' => ['id' => ...]`
- `info()`: change `$this->config->getSection('name')` → `($this->config->getSection('project')['name'] ?? '')`

### 7. `tests/fixtures/config.yaml`
- Remove top-level `name:` line
- Rename `settings:` → `project:`
- Add `name: Example Project` as first entry inside `project:`

### 8. `tests/fixtures/config-minimal.yaml`
- Rename `settings:` → `project:`

### 9. `tests/bats/cli.bats`
- Line ~51: `yaml_get ... "settings.id"` → `yaml_get ... "project.id"`
- Inline `info` test config YAML: rename `settings:` → `project:`, move `name:` inside

### 10. `docs/user/en/configuration.md`
- Update example config block: move `name` inside `project:`, rename `settings:` → `project:`
- Update the Config Sections table: `settings` → `project`
- Update any prose references to `settings` section

---

## Notes

- No env var changes — `INVRT_ID` and all others remain the same
- `getSection('project')` returns the full raw project block including `name`
- Callers wanting the display name use `$this->config->getSection('project')['name'] ?? ''`
