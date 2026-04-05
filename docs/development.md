## Development Information

See the [Coding Standards][CODING_STANDARDS.md]

## Release Automation

Patch releases are automated with Taskfile commands:

```bash
task version:bump
```

`task version:bump` performs the full release flow:

1. Bump semantic version to next patch version.
2. Update version references in tracked files.
3. Run all tests and checks (`task test`).
4. Build and publish Docker images (`ronan4000/invrt:latest` and `ronan4000/invrt:<version>`).
5. Publish the `ddev-invrt` addon and push a matching release tag.

Required environment variables:

```bash
export DOCKERHUB_USERNAME=your-user
export DOCKERHUB_TOKEN=your-token
export GITHUB_TOKEN=your-github-token
```

If publishing credentials are missing, release publish steps fail fast with a clear error.
