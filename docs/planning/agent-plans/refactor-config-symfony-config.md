# Plan: Refactor Config Handling to use symfony/config

## Problem

Config handling in `EnvironmentService` is manual, verbose, and has accumulated several
inconsistencies with the documentation. The goal is to:

1. Audit and fix doc/code inconsistencies
2. Simplify and clarify config handling tests
3. Rewrite config handling using the `symfony/config` component

---

## Inconsistencies Found (Doc vs Code vs Tests)

### Critical bugs
- **`settings` section is never applied.** Docs say it provides base values overridden by
  environment/profile/device. The code only processes `environments.{env}`, `profiles.{profile}`,
  and `devices.{device}` — the `settings` section is silently skipped.
- **`INVRT_LOGIN_URL` not returned from `getEnvironmentArray()`.** It is set via `putenv` inside
  `applyConfigValues`, but the return value of `getEnvironmentArray()` doesn't include it (or most
  other config vars: `INVRT_MAX_PAGES`, `INVRT_VIEWPORT_WIDTH`, etc.).

### Defaults mismatch (docs vs code)
- `viewport_width` default: docs say `1024`, code uses `1920`
- `viewport_height` default: docs say `768`, code uses `1080`
- `login_url` default: docs say `{INVRT_URL}/user/login`, code defaults to `''`

### File extension inconsistency
- Docs reference table says `config.yml`, code (and the example in docs) uses `config.yaml`

### Missing from docs
- `max_concurrent_requests` is exported by code as `INVRT_MAX_CONCURRENT_REQUESTS` but not listed
  in the Configuration Options Reference table

### Test issues
- `InvrtCliTest` largely tests raw `Yaml::parse()` calls — testing Symfony's YAML library, not
  inVRT logic. These tests add noise and should be removed or replaced.
- Tests reference a nested `auth: { username, password }` structure in profiles that the code
  doesn't support (it expects flat `username`/`password` keys directly in the profile).

---

## Phase 1: Audit and Fix Doc/Code Inconsistencies

- Update `docs/configuration.md` to fix the `config.yml` → `config.yaml` reference
- Decide and align defaults for `viewport_width`, `viewport_height`, `login_url`
- Add `max_concurrent_requests` to the Configuration Options Reference table
- Fix the config example in docs to remove the extra space in the `stage` block  
  (`     max_pages: 20` is indented incorrectly)
- Document that `settings` section applies as a base layer

---

## Phase 2: Rewrite Config Handling Tests

- Delete `InvrtCliTest.php` — it tests YAML library internals and trivial array operations,
  not inVRT behavior
- Rewrite `EnvironmentServiceTest.php` to be cleaner and cover all documented behavior:
  - Settings section values are used as base (can be overridden)
  - Environment values override settings
  - Profile values override environment
  - Device values override profile
  - All documented env vars are present in the returned array
  - Missing sections use defaults

---

## Phase 3: Rewrite App Config Handling with symfony/config

### New dependency
Add `symfony/config` to `composer.json`.

### New class: `src/Service/ConfigDefinition.php`
Implement `Symfony\Component\Config\Definition\ConfigurationInterface` using `TreeBuilder` to:
- Define the full config schema with all valid keys, types, and defaults
- Replace the manual `$configKeys` array in `EnvironmentService`
- Provide normalization and validation out of the box

### Refactor `EnvironmentService`
- Use `Processor` + `ConfigDefinition` to load and validate the YAML config
- Apply section merging (settings → environment → profile → device) via standard PHP array merging
  after each section is extracted and processed through the definition
- `getEnvironmentArray()` should return ALL documented env vars (including login_url, viewport_*,
  max_pages, etc.) — not just the current 11
- Remove the manual dot-notation traversal methods (`getConfigValueRaw`, `getConfigValue`)
- Remove `processSectionConfig` — replace with simple array merge

---

## Completion

- [ ] Mark the three sub-tasks done in TODO.md
- [ ] Check off the parent `[-]` item in TODO.md once all sub-tasks complete
