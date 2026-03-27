# TODO

This file tracks planned features, known bugs, test gaps, and technical debt for inVRT.
It is maintained for use by both AI agents and human developers.

- Use `- [ ]` for open items and `- [x]` for completed items.
- Reference the relevant file or doc when adding an item.

---

## Features

- [ ] **WordPress support** — Full CMS support for WordPress alongside Drupal and Backdrop. See `README.md`.

---

## Bugs

- [ ] **Move reference script logic to PHP runner** — `src/invrt-reference.sh` contains startup logic that should live in the PHP runner app. See `src/invrt-reference.sh:3`.

---

## Tests

- [ ] **E2E: ReferenceCommandTest** — End-to-end test for `invrt reference` (Playwright screenshot capture). See `plans/invrt-test-suite-plan.md`.
- [ ] **E2E: TestCommandTest** — End-to-end test for `invrt test` (BackstopJS VRT comparison). See `plans/invrt-test-suite-plan.md`.

---

## Tech Debt

_(none yet)_
