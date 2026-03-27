# Plan: Create TODO.md

## Goal

Create a top-level `TODO.md` that tracks features, bugs, tests, and tech debt for inVRT.
Usable by both AI agents and human developers. Uses GitHub-style checkboxes.
Organized by category: Features, Bugs, Tests, Tech Debt.

## Content

### Source items (from codebase research)

**Features:**
- WordPress support — marked FUTURE FEATURE in README.md

**Bugs:**
- Move `invrt-reference.sh` startup logic into PHP runner app — noted in `src/invrt-reference.sh:3`

**Tests:**
- E2E `ReferenceCommandTest` — missing from `tests/E2E/` (CrawlCommand, Config, Init exist; Reference and Test do not)
- E2E `TestCommandTest` — missing from `tests/E2E/`

**Tech Debt:**
- (none identified yet beyond the reference.sh item)

## File created

- `/workspaces/invrt/TODO.md`

## Format

- GitHub checkboxes: `- [ ]` / `- [x]`
- Sections: Features, Bugs, Tests, Tech Debt
- Each item: bold label, short description, reference to source file or doc where relevant
- Header note explaining the file's purpose for AI and human readers

## Status

✅ Complete — `TODO.md` created at project root.
