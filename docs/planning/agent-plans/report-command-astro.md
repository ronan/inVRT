# Plan: `invrt report` builds an Astro report from latest test results

## Goal

Add an `invrt report` command that builds the existing Astro-based
report site under `src/js/report-builder/` and writes the rendered
output into the project's `INVRT_DIRECTORY` so that
`<INVRT_DIRECTORY>/index.html` opens the report.

This replaces the previous single-page HTML report (whose plan lives at
`docs/planning/agent-plans/single-page-html-report.md`); that output is
no longer produced.

## Behavior

`invrt report` (no arguments) does the following:

1. Boots like other commands (config required, login not required).
2. Runs `npx astro build` with:
   - `cwd`: `src/js/report-builder/` (resolved via the app dir).
   - `env`: inherits process env, plus `INVRT_DIRECTORY` set to the
     resolved plan directory (so the existing `loadReport.ts` can read
     `plan.yaml`, results, references, etc.).
3. Copies 
    `src/js/report-builder/dist/index.html` into
   `INVRT_DIRECTORY/index.html`, with overwriting.
4. Logs the path to the report to stdout and exits 0.

Failure modes:
- astro build fails → return non-zero, log stderr.
- dist/index.html directory missing after build → return non-zero, log error.

## Implementation

### 1. `Runner::report()`

Add a new public method to `src/core/Runner.php`:

```php
public function report(): int
```

- Resolves `INVRT_DIRECTORY` from `$this->config`.
- Resolves the report-builder dir as `$this->appDir . '/report-builder'`
  (since `appDir` is `src/js/`).
- Builds and runs a `Symfony\Component\Process\Process` for
  `npx astro build`, with cwd = report-builder dir, env =
  `$this->config->all()` (which already includes `INVRT_DIRECTORY`).
- Streams stdout/stderr through `$this->logger` at info/debug
  verbosity.
- On success, copies `report-builder/dist/index.html` →
  `INVRT_DIRECTORY/index.html` using
  `Symfony\Component\Filesystem\Filesystem::copy($source, $target, true)`.
- Logs `📝 Report written to <path>/index.html` at notice level.

We do not introduce a new service for this — it's a one-off shell out,
similar to how `Runner::approve()` invokes `PlaywrightRunner` directly.

### 2. `ReportCommand`

Add `src/cli/Commands/ReportCommand.php`:

```php
#[AsCommand(name: 'report', description: 'Build the HTML report from the latest test results')]
class ReportCommand extends BaseCommand
{
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($r = $this->boot($opts, $io)) !== Command::SUCCESS) return $r;
        return $this->runner->report() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
```

### 3. Wire up in `src/cli/invrt.php`

Add the autowire + `addCommand` lines for `ReportCommand`.

### 4. Documentation updates

- Update `docs/user/en/usage.md`: rewrite the existing `report`
  section to describe the new behavior. Drop `--output` and `--open`
  options (they aren't part of this todo). The `--profile`,
  `--device`, `--environment` options remain via `InvrtInput`.
- Update `docs/spec/APP_SUMMARY.md`: change the description so it
  matches the astro-build behavior.

- Update docs/spec/Application.yaml

### 5. Tests

Add a Bats test in `tests/bats/cli.bats` (or the workflow file if a
real run is already set up) that:

1. Initializes a project against the fixtures website.
2. Runs `invrt baseline` (or the cheaper combination of crawl +
   reference + test) so there are results to render.
3. Runs `invrt report`.
4. Asserts exit 0 and that `<plan-dir>/index.html` exists and is
   non-empty.

Astro build is slow; if the workflow test gets too heavy, scope this
to a single minimal happy-path test against `tests/fixtures/website/`.

## Out of scope

- `invrt report open` (separate todo).
- `invrt playwright` passthrough (separate todo).
- Any changes to the astro report internals.
