# Separate crawling from tree-building

## Goal

Split the current monolithic crawl step in `src/js/crawl.js` into two
distinct phases:

1. **Crawl** — discover URLs and capture page titles. Produce a flat list
   of `{ path, title }` records.
2. **Build tree** — turn that flat list into the nested `pages:` tree in
   `plan.yaml`, derive the site title from the home page, and clean up
   page titles for use as Playwright test names.

## Behaviour

### Crawl phase

- For every successfully fetched HTML page, record:
    - `path` — the normalized URL path (current behaviour).
    - `title` — `document.title` from the rendered page (raw, untrimmed).
- Output a flat list of those records (sorted by path) to stdout as
  yaml, so the existing `INVRT_CRAWL_FILE` redirection still produces a useful artifact.

    Example YAML format:

```yaml
/: My Website
/about.html: About Us | My Website
/staff/leadership/: Our Leadership | My Website
'/url/with/a:/': Weird Url Page | My Website
```

- Crawl no longer reads or writes `plan.yaml`. Seed paths come from
  `plan.yaml` only via a small read at startup (existing behaviour
  preserved); no merging of discovered pages into the plan.

### Build-tree phase

A new node script `src/js/build-plan-tree.js`:

- Reads the flat list (path + title) from stdin.
- Reads the existing `plan.yaml` (exits with an error a default if missing).
- Derives the **site title**:
    - Use the title of the `/` page exactly as captured.
    - Store on `plan.project.title` only when not already set.
- Cleans each non-home page title:
    - Remove every occurrence of the site title from the page title.
    - Trim whitespace and non-alphanumeric characters from both ends.
    - If the cleaned title is empty, fall back to the raw title.
- Inserts each path into `plan.pages` using the existing
  `insertPathIntoTree` logic (moved from `crawl.js`), and stores the
  cleaned title as `title:` on the leaf node.
    - Don't overwrite a `title` that already exists in the plan
      (user-edited values win).
- Writes `plan.yaml`.

### Runner wiring

`InVRT\Core\Runner::crawl()` becomes a two-step pipeline:

1. Run `crawl.js`, capturing stdout (the flat list).
2. Pipe that into `build-plan-tree.js`.

The combined stdout (the flat list) is still written to
`INVRT_CRAWL_FILE` so the existing artifact is preserved.

`NodeRunner` already supports stdin via the existing
`generate-playwright` flow — reuse the same approach.

### Playwright test names

In `src/js/generate-playwright.js`:

- `extractPagesFromPlan` already walks the tree; extend the page record
  with the `title` it finds on the leaf node.
- The generated `test(...)` call uses `title` when present, otherwise
  falls back to the current short id.
- Uniqueness: if two pages resolve to the same title, append ` (id)`
  to deduplicate so Playwright doesn't reject duplicate test names.
- Snapshot filenames keep using the id (no change to artifacts).

## Out of scope

- Tidy up plan.yaml (separate todo).
- Anything beyond crawl/tree-build/test-name behaviour.

## Tests

- Existing bats workflow tests (`tests/bats/workflow.bats`) should keep
  passing.
- Add or update a workflow test that asserts:
    - `plan.yaml` records page titles after a crawl.
    - `plan.project.title` is set to the home page title.
    - The generated playwright spec uses the cleaned title as the test
      name.

## Files touched

- `src/js/crawl.js` — strip tree code; emit flat list with titles.
- `src/js/build-plan-tree.js` — new; tree builder + title cleanup.
- `src/core/Runner.php` — pipe crawl into build-plan-tree.
- `src/core/Service/NodeRunner.php` — only if a small helper is needed
  to chain two scripts; prefer doing this in `Runner::crawl()`.
- `src/js/generate-playwright.js` — read titles, use as test names.
- `tests/bats/workflow.bats` — assertions for titles.
- `docs/developer/en/APP_SUMMARY.md` — describe the new behaviour.
