# Plan: Rebuild Crawler (Page IDs + Tree Structure)

## Goal

Complete the remaining crawler TODO items:

1. Generate stable page IDs during crawl and store them in plan.yaml.
2. Restructure plan.yaml pages into a tree-style format that follows the Plan YAML specification.

## Scope

- Update crawler output shape in plan.yaml from flat path keys to nested branch objects.
- Keep existing crawl behavior for discovery limits and exclusion rules.
- Keep generate-playwright working against the new tree shape.
- Preserve existing user metadata where possible.

## Implementation Steps

1. Documentation-first updates
- Update docs/user/en/usage.md to describe:
  - page IDs written during crawl,
  - tree-structured pages in plan.yaml.
- Update docs/spec/APP_SUMMARY.md to describe crawler output shape and ID behavior.

2. Add path-to-tree builder in src/js/crawl.js
- Introduce helpers to insert a discovered URL path into a nested pages tree:
  - split path into branch segments,
  - create intermediary branch nodes when needed,
  - choose child landing key based on resolved URL form:
    - empty key for non-trailing parent landing page,
    - slash key for trailing slash parent landing page,
    - query-prefixed key for query-only child nodes.
- Merge sibling paths under common parents when prefix rules apply (as required in TODO).

3. Generate and persist page IDs during crawl
- Reuse current deterministic ID strategy (same alphabet/hash style used elsewhere in JS) with project seed from INVRT_ID.
- For each discovered testable page node, ensure id is present.
- Keep existing profiles array behavior and merge profile access.

4. Preserve user-defined metadata
- When inserting/updating a node:
  - keep non-managed keys unchanged,
  - only add/merge managed keys (id, profiles, title when available in future).

5. Update src/js/generate-playwright.js for tree input
- Replace flat key extraction with recursive traversal of pages tree.
- Collect testable paths from landing keys and leaf keys.
- Continue respecting INVRT_MAX_PAGES and deterministic test IDs.

6. Tests
- Add/adjust Bats workflow tests to verify:
  - crawl writes id fields into plan.yaml,
  - crawl outputs tree-like nested structure for representative nested URLs,
  - generate-playwright still creates a valid spec from tree-based plan.yaml.

7. TODO tracking
- Move completed items from TODO.md to docs/planning/TODO-DONE.md:
  - Generate page ids during crawl and add them to plan.yaml.
  - Improve crawler to build a tree-like structure for nested pages.

8. Validation
- Run task test and fix regressions.

## Acceptance Criteria

- plan.yaml contains stable page ids for discovered pages.
- plan.yaml pages are tree-structured with grouped prefixes per TODO rules.
- generate-playwright works from tree-based plan.yaml.
- task test passes.
