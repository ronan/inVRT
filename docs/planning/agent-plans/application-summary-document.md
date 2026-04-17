# Plan: Application Summary Document

## Goal

Create a single markdown file (`docs/APP_SUMMARY.md`) that describes the inVRT application's functionality in sufficient detail for an agent to understand or recreate it. The document should be:

- Brief and non-repetitive
- Optimized for agent consumption (structured, precise, machine-readable)
- Focused on functionality, not implementation details

## Document Outline

1. **What it is** — one-line description and purpose
2. **Workflow** — the core 4-step flow (init → crawl → reference → test)
3. **Commands** — each command, its inputs, outputs, and behavior
4. **Options** — shared CLI flags (--profile, --device, --environment) and verbosity
5. **Configuration** — config file format, sections, key settings, precedence rules
6. **Authentication** — how login works, cookie handling, re-login behavior
7. **Data layout** — directory structure under `.invrt/`
8. **Configuration reference table** — all config keys, env vars, defaults, scope

## Steps

- [x] Read docs/usage.md and docs/configuration.md
- [ ] Write docs/APP_SUMMARY.md
- [ ] Add TODO item and mark complete
