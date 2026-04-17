# Update "Adding a New Command" guidance

## Objective

Update `AGENTS.md` so the command authoring instructions match the current code standard and Symfony 8 command patterns used in this repo.

## Plan

1. Inspect the current command base classes and concrete command implementations.
2. Replace the outdated `configure()` / `getScriptName()` guidance with the attribute-based `#[AsCommand]` / `__invoke()` pattern.
3. Document when to extend `BaseCommand`, when to use `withEnv()`, and when a command can stand alone.
4. Note the shared mapped input object and the requirement to add verbosity levels to console output.

## Notes

- The existing section still describes an older command lifecycle that no longer matches `src/Commands`.
- Keep the update focused on current repo conventions rather than generic Symfony examples.