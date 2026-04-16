# Plan: complete remaining ready Baseline/Test/Report flow tasks

## Problem

The `### Baseline/Test/Report flow` section in `TODO.md` still has five ready items:

1. Add a URL argument to `invrt init`
2. Auto trigger `invrt init` when `invrt crawl` is run for the first time
3. Implement `invrt approve`
4. Add an `invrt baseline` command
5. Implement an interactive init mode

These changes touch command wiring, core runner behavior, first-run UX, documentation, and end-to-end coverage.

## Approach

1. **Document the new workflow first**
   Update `docs/usage.md` and `docs/APP_SUMMARY.md` to describe:
   - `invrt init <url>`
   - interactive init prompting for a URL when none is supplied
   - `crawl` auto-initializing on first run
   - `approve` approving the current Backstop baseline
   - `baseline` ensuring reference, test, and approve happen in sequence

2. **Expand command input and wiring**
   - Add support for a positional URL argument on `init`
   - Add new Symfony commands for `approve` and `baseline`
   - Register the new commands in `cli/invrt.php`
   - Keep shared profile/device/environment options working with the new commands

3. **Rework initialization flow**
   - Replace the hard-coded example `config.yaml` scaffold with a fresh minimal config
   - Write the provided URL to `environments.<selected-environment>.url`
   - Ensure the selected environment/profile/device keys exist in the new config
   - Add interactive prompting in `init` when the URL argument is missing

4. **Support first-run crawl bootstrapping**
   - Let `crawl` run without a pre-existing config
   - When config is missing, trigger init before the crawl continues
   - Reuse the interactive URL prompt during that bootstrap path
   - Preserve current failure behavior when initialization or crawl prerequisites still fail

5. **Add approve and baseline core behavior**
   - Add runner support for `approve`
   - Make `baseline` enforce the intended flow:
     - run `reference` if required
     - run `test` if required
     - run `approve`
   - Keep command exit codes and logger output consistent with the existing console style

6. **Cover the new behavior with tests**
   - Update BATS coverage for:
     - `init <url>`
     - interactive init
     - crawl first-run auto-init
     - `approve`
     - `baseline`
   - Add or update focused PHPUnit coverage where core runner branching is better tested in PHP
   - Run `task test`

7. **Check off the todo items**
   - Mark the five completed items as done in `TODO.md`

## Notes

- The URL should be stored under `environments.<selected-environment>.url`, not `settings.url`.
- When `crawl` auto-triggers init without a config, it should prompt for the URL interactively.
- Existing first-run behavior already chains `test -> reference -> crawl`; the new work should extend that chain to include `init` only when needed.
