# Plan: Add Project ID to Distinguish Final Reports

## Goal

Generate a stable project identifier during `init` and persist it to `config.yaml` as an `id` key in the `settings` section. Use the ID in BackstopJS reports via `id` so each project's reports are distinguishable.

## Design

### ID Generation

- Short, human-readable, stable after generation.
- Low chance of collisions across projects, even with the same url. (eg: http://localhost)
- Based on the **URL hostname** and a **random 2-byte seed** (generated once at init).
- The seed provides uniqueness across projects with the same domain.
- The hostname is normalized (lowercased, dots replaced with hyphens, non-alphanumeric stripped).
- The normalized hostname is converted to a 32 bit integer with crc32 
    ```php
    $url_hash = intval(hash("crc32b", $str), 16);
    ```
- The final id uses `squids` to combine the $url_hash and randomj seed into a short, readable id:
  - Use squids php (https://github.com/sqids/sqids-php)
  - Use a custom alphabet of only lowercase characters (swxaiodyktzhgujfblrpmcqevn)
- Example: 
  - url: https://mydev.example.com
  - random seed: 0xA4D9 (49153)
  - hashed_url: 0x484fe7c3 (1213167555)
  - Final id: ujusoevsmttkt

### Storage

- Saved as `id` key in setting in `.invrt/config.yaml`.
- Example:
  ```yaml
  name: my-project
  settings: 
    id: example-com-a1b2c3d4
  ```

### Where it's generated

- `Runner::init()` — generates the ID when writing the initial config.yaml.
- A static helper `Runner::generateProjectId(string $url): string` does the generation.

### Where it's consumed

- `backstop-config.js` reads `INVRT_ID` env var and sets `id` to it.
- `Runner::info()` includes the `id` in its summary output.

### Configuration plumbing

The `id` key is already defined in `ConfigSchema::DEFAULTS` (as `''`) and in the schema tree builder. It already resolves to `INVRT_ID` via `Configuration::asEnv()`. No schema changes needed.

1. **`Runner::init()`** — write `id` into the `settings` section of the YAML dump.
2. **`backstop-config.js`** — use `INVRT_ID` for `id`.
3. **`Runner::info()`** — include `id` in the returned array.

## Files Changed

| File | Change |
|------|--------|
| `src/core/Runner.php` | Add `generateProjectId()`, call it in `init()`, include `id` in `info()` |
| `src/core/Configuration.php` | Read settings-level `id` from parsed YAML and include in resolution |
| `src/js/backstop-config.js` | Use `INVRT_ID` for `id` |
| `tests/bats/cli.bats` | Assert `id` is present in config.yaml after init |
| `docs/user/en/configuration.md` | Document the `id` field |
| `docs/spec/config.example.yaml` | Add `id` example |
| `docs/spec/Application.yaml` | Already has `id` — no change needed |

## Out of Scope

- Migrating existing projects (no `id` in old configs → `INVRT_ID` stays empty).
- The separate "improve page ids" TODO item with node-shorthash.
