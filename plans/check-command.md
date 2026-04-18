# Plan: Implement `invrt check`

## Problem

No command exists to verify connectivity and collect basic site metadata before crawling. The `check` command fills this gap by loading the site homepage and recording useful information to a `check.yaml` file.

## Behaviour

- Load the configured site URL via an HTTP request (PHP, using curl extension).
- Resolve the final URL after following redirects; detect permanent (301) redirects.
- Check whether HTTPS is available/used.
- Extract the site `<title>` from the HTML response.
- Record the check date.
- Write `.invrt/data/<environment>/check.yaml` with these fields.
- Auto-run after `init` when the check file doesn't yet exist.
- Auto-run before `crawl` when the check file doesn't yet exist.
- CMS detection deferred to a follow-up task.

## check.yaml shape

```yaml
url: https://example.com          # final resolved URL (after redirects)
title: "My Site"                  # <title> text
https: true                       # true if the final URL uses https
redirected_from: http://example.com  # present only when a permanent redirect was followed
checked_at: "2026-04-17T23:38:44+00:00"
```

## check.yaml path

`.invrt/data/<environment>/check.yaml`

Stored at `INVRT_CHECK_FILE` (new config key).

## Tasks

1. **ConfigSchema** — add `check_file` default:  
   `'check_file' => 'INVRT_CRAWL_DIR/../check.yaml'`  
   (resolves to `.invrt/data/<env>/check.yaml` because `INVRT_CRAWL_DIR` = `.../data/<env>/<profile>`)

   Wait - INVRT_CRAWL_DIR is `data/<environment>/<profile>`, and check.yaml should be at `data/<environment>/check.yaml`. So the correct default would be `INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/check.yaml`.

2. **Runner::check()** — new method that:
   - Uses PHP's curl to GET the URL, following redirects, recording 301 redirect chain.
   - Extracts `<title>` via regex.
   - Detects https on final URL.
   - Writes check.yaml to `INVRT_CHECK_FILE`.
   - Returns 0 on success, 1 on failure.

3. **CheckCommand** — new command class:
   - `name: 'check'`, extends `BaseCommand`, `$requiresLogin = false`.
   - Calls `$this->runner->check()`.

4. **Auto-run in Runner::init()** — call `$this->check()` after successful init.

5. **Auto-run in Runner::crawl()** — call `$this->check()` if `INVRT_CHECK_FILE` doesn't exist yet.

6. **Register** in `src/cli/invrt.php` and in `CommandTestCase`.

7. **Document** in `docs/user/en/usage.md` — add `check` section, update Workflow Overview.

8. **Tests** — E2E `CheckCommandTest` covering:
   - Writes check.yaml with expected fields on success.
   - Fails gracefully when URL is unreachable.
