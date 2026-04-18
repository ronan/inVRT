# Plan: YAMLify the App Summary

## Goal

Create machine-readable YAML specifications derived from `docs/spec/APP_SUMMARY.md` so an agent can scaffold the current inVRT application shape without parsing prose.

## Approach

1. Add `docs/planning/spec/Commands.yaml` with one entry per CLI command, including command metadata, arguments/options, initialization/login requirements, prerequisite relationships, and key artifacts.
2. Add `docs/planning/spec/Configuration.yaml` with the configuration model, precedence rules, runtime context values, and per-key schema metadata for values supported in `settings`, `environments.*`, `profiles.*`, and `devices.*`.
3. Update `docs/README.md` so the new planning/spec files are discoverable from the docs index.

## Notes

- The proposal points at `docs/spec/APP_SUMMARY.md`; that file exists and is the source of truth for this task.
- This is documentation/spec work only. No code behavior changes are expected.
