# Plan: Convert crawled_urls.txt to plan.yml (Site Tree)

## Todo item

`### Advanced flow` — first item:

> [#] Create a function that converts crawled_urls.txt to the format in SITE_TREE_FILE_SPEC.md
>   - [ ] Name the file 'plan.yml' and put it at the top of the .invrt directory
>   - [ ] Update the document when new paths are found when crawling with different profiles

---

## Overview

After every `invrt crawl` run, generate (or update) a `.invrt/plan.yml` that represents the crawled URLs as a nested YAML site tree per `docs/SITE_TREE_FILE_SPEC.md`.

- The file lives at `$INVRT_DIRECTORY/plan.yml` (not inside the per-profile/env data directory).
- On subsequent crawls (different profile, different environment, or same run again), new paths discovered are merged in; no existing entries are removed.
- Only path structure is written; no metadata (titles, settings) is added by the tool.

---

## Implementation steps

### 1. Add `SiteTreeService` to `core/src/Service/`

New static service class `InVRT\Core\Service\SiteTreeService`.

**Method: `urlsToTree(array $paths): array`**

Converts a flat list of URL paths (as returned by `Runner::parseUrlsFromLog()`) into a nested PHP array that matches the SITE_TREE_FILE_SPEC.md structure.

Rules to implement:
- Split each path by `/` into segments.
- If a path ends with `/` it is itself a landing page AND a container — use the `/.:` convention for its own entry within the parent.
- The root `/` maps to `/.:` at the top level.
- Query strings (`?page=1`) become child keys under the path-without-query parent.
- If a segment has no intermediary landing page but has children, skip the `/.` key for that segment.
- Leaf nodes (no children) are represented as `null` (YAML shorthand — no value).
- Use "flat branches" for intermediate path segments that have only one child and no landing page of their own.

**Method: `mergeTrees(array $existing, array $incoming): array`**

Deep-merges two tree arrays. Incoming keys not already present are added; existing keys are never removed.

**Method: `treeToPaths(array $tree, string $prefix = ''): array`**

Inverse helper (for testing): converts a tree back to a sorted flat list of path strings.

**Method: `readPlanFile(string $file): array`**

Reads and parses `plan.yml` using Symfony Yaml; returns empty array if file does not exist.

**Method: `writePlanFile(string $file, array $tree): void`**

Serializes the tree to YAML and writes to the file using `Symfony\Component\Yaml\Yaml::dump()` with appropriate indent level. Uses `Symfony\Component\Filesystem\Filesystem::dumpFile()`.

---

### 2. Call `SiteTreeService` from `Runner::crawl()`

After the existing `file_put_contents($crawlFile, implode("\n", $paths))` line, add:

```
$planFile = Path::join($this->config->get('INVRT_DIRECTORY', ''), 'plan.yml');
$existing = SiteTreeService::readPlanFile($planFile);
$incoming = SiteTreeService::urlsToTree($paths);
$merged   = SiteTreeService::mergeTrees($existing, $incoming);
SiteTreeService::writePlanFile($planFile, $merged);
$this->logger->info("✓ Site tree written to $planFile");
```

---

### 3. Document in `docs/APP_SUMMARY.md` and `docs/usage.md`

In `APP_SUMMARY.md` — add to the `crawl` command section:

> After crawling, writes/updates `.invrt/plan.yml` — a nested YAML site tree (see `docs/SITE_TREE_FILE_SPEC.md`). New paths discovered on subsequent crawls are merged in; existing paths are preserved.

In `docs/usage.md` — add a brief note under `invrt crawl` that the site tree file is created/updated.

---

### 4. Tests

**Unit: `tests/Unit/Service/SiteTreeServiceTest.php`**

Test `urlsToTree()` with representative cases:
- Root path `/`
- Simple paths: `/about`, `/about/contact`
- Trailing slash paths: `/past-events/2021/`
- Query string paths: `/directory?page=1`, `/directory?page=2`
- Mixed deep paths with gaps (no intermediary landing pages)
- That `mergeTrees()` adds new paths without removing old ones

**BATS: `tests/bats/site-tree.bats`** _(if bats tests cover crawl behaviour)_

No separate BATS test needed — the crawl BATS tests can assert that `plan.yml` exists after a crawl run.

Extend existing crawl BATS test (`tests/bats/*.bats`) to assert:
- `$INVRT_DIR/plan.yml` exists after `invrt crawl`
- Running crawl a second time does not remove paths from `plan.yml`

---

### 5. Mark todo items done

- Change the parent `[#]` to `[x]`
- Change both sub-item `[ ]` entries to `[x]`
