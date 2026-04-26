# Plan: `invrt report` — Single-Page HTML Test Report

Implements TODO: "Create a 1 page html report for all existing test results"
Original proposal: [docs/planning/proposals/astro-shadcn-report.md](../proposals/astro-shadcn-report.md)

## Goal

Produce a single self-contained `report.html` that summarises VRT results for the
configured project/profile/device(s). Shareable via Slack/email — no server, no
external file deps for screenshots (images embedded as data URIs or co-located).

## Recommendation: defer Astro+shadcn

The proposal calls for Astro + shadcn/ui. That introduces a Node build pipeline,
a new tool config, and ~hundreds of MB of dev deps for what is essentially a
templated HTML page. The existing `scratch/report.html` proves the layout works
with Tailwind + daisyUI via CDN in ~80 lines.

**Recommended approach:** generate a single HTML file directly from a Node
script using a small handlebars template + Tailwind/daisyUI CDN. Handlebars is
already a devDep. Output is a self-contained `.html` file with screenshots
inlined as base64 data URIs so it works offline.

(If you want the Astro path I'll write a separate plan — flag it before I start.)

## UX

```
invrt report                       # generate report.html for current env/profile/device
invrt report --open                # also open it in default browser
invrt report --output path.html    # custom output path
```

Default output: `INVRT_DIRECTORY/report.html` (i.e. `.invrt/report.html`).

Inputs honour the standard `-e`, `-p`, `-d` shared options.

## Architecture

Follows existing command pattern:

1. **`src/cli/Commands/ReportCommand.php`** — invokable command extending
   `BaseCommand`, `$requiresLogin = false`. Adds `--open`/`-o` and `--output`
   flags. Calls `$this->runner->report($open, $outputPath)`.

2. **`src/core/Runner.php`** — new `report(bool $open, ?string $outputPath): int`.
   Resolves the output path, runs `src/js/generate-report.js` via the existing
   Node runner. Optionally opens the file with `open` (macOS) / `xdg-open` /
   `start` based on PHP_OS_FAMILY.

3. **`src/js/generate-report.js`** — reads env vars (same pattern as other JS
   scripts), loads `plan.yaml`, `.last-run.json`, scans bitmap dirs, renders
   handlebars template, writes single HTML file. No stdin pipeline needed.

4. **`tooling/templates/report.hbs.html`** — handlebars template based on
   `scratch/report.html`, extended with:
   - Header: project name, environment, profile, device, generated timestamp
   - Stats cards: Total / Unchanged / Changed / Missing
   - Filterable list (client-side JS, vanilla, ~30 lines) by status + text search
   - Per-page card: title, path, status badge, thumbnail of reference image,
     and (when failing) reference + actual + diff side-by-side

5. **Register** `ReportCommand` in `src/cli/invrt.php`.

## Data sources

| Data | Source |
|------|--------|
| project meta, page tree | `INVRT_PLAN_FILE` (plan.yaml) |
| pass/fail per page | `INVRT_CRAWL_DIR/results/.last-run.json` + per-test result dirs |
| reference image | `INVRT_CAPTURE_DIR/reference/<device>/<id>.png` |
| actual image | `INVRT_CAPTURE_DIR/<env>/<device>/<id>-actual.png` (when failed) |
| diff image | sibling `*-diff.png` |
| environments/profiles/devices | plan.yaml |

Page IDs already stable via `build-plan-tree.js` `encodeId()`. Reuse that module
to map plan paths ↔ image filenames.

Status per page:
- `unchanged` — has reference, no failure entry
- `changed` — appears in `failedTests` of `.last-run.json`
- `missing-reference` — page in plan, no reference png yet
- `untested` — page in plan, no actual run for this env/device

## Image embedding

Read png files, base64-encode, inline as `data:image/png;base64,...`. Skip files
> ~5 MB to keep the report sane (very unlikely for screenshots).

For thumbnails (page list), use the same source image — browsers downscale via
CSS. Avoid adding sharp/jimp just for thumbnails; size on disk is fine.

## Single-file guarantee

- CSS/JS via CDN tags (Tailwind browser build + daisyUI). The proposal said
  "bundle CSS and JS into a single standalone HTML"; CDN is simpler and meets
  the "shareable" intent (file works offline once cached, opens fine via
  `file://` with internet). If true offline-no-internet is a hard requirement,
  inline minified Tailwind via a fetch step at build time — I'll add that as a
  follow-up if needed.
- All images embedded.
- Filter/search JS inline in a `<script>` tag in the template.

## Tests

Bats E2E in `tests/bats/workflow.bats` (or new `report.bats` section):

1. After a successful `invrt reference` + `invrt test` run against the fixture
   site, run `invrt report`. Assert:
   - Exit code 0
   - `report.html` exists at expected default path
   - File contains the project name, all page paths, the device label
   - File contains at least one `data:image/png;base64,` URI

2. `invrt report --output /tmp/.../custom.html` writes to the custom path.

3. Running `report` before any reference exists still produces a report
   (degraded — pages flagged as `missing-reference`), exit 0.

Don't unit-test the template internals.

## Docs

- Add to [docs/user/en/usage.md](../../user/en/usage.md): brief `invrt report`
  section with example.
- Add to [docs/developer/en/APP_SUMMARY.md](../../developer/en/APP_SUMMARY.md):
  describe behaviour, inputs, outputs, default path.
- TODO: move "Create a 1 page html report" entry to `TODO-DONE.md` on
  completion.

## Out of scope (future TODO items, already tracked)

- Re-running tests from the report
- Approving diffs from the report
- Comparing environments/profiles
- Astro/shadcn dashboard with rich interactivity

## Open questions

1. Confirm the recommendation to skip Astro/shadcn. OK to proceed with the
   handlebars + CDN approach?
2. Default output path `.invrt/report.html` OK, or prefer `.invrt/report/index.html`?
3. Should the report cover only the active env/profile/device (simplest), or
   aggregate every environment/profile/device that has results on disk? I
   recommend starting with the active triple and adding aggregation as a
   follow-up.
