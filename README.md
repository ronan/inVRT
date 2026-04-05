# inVRT v1.0.2

A CLI tool for Visual Regression Testing (VRT) of CMS-driven websites as an authenticated or anonymous user.

Configure a url, username and password for one or more user profiles and site environments and inVRT will crawl the site, capture reference images and can be used to check for any visual changes on any page of the site.

Supports Drupal, Backdrop, and WordPress (FUTURE FEATURE).

## Quick Start

### Run with Docker (no install required):

```bash
# 1. Go to your project repository root
cd ~/my-project

# 2. Initialise a project in the current directory
docker run -it --volume .:/dir ronan4000/invrt init

# 2. Edit .invrt/config.yaml — set your URL and any credentials

# 3. Crawl the site, generate the reference images and run a test
docker run -it --volume .:/dir ronan4000/invrt test --profile=authenticated
```

---

## Commands

| Command | Description |
|---|---|
| `invrt init` | Scaffold `.invrt/` directory and `config.yaml` |
| `invrt crawl` | Crawl the site and build a URL list |
| `invrt reference` | Capture baseline screenshots |
| `invrt test` | Compare current screenshots against the baseline |
| `invrt config` | Display the resolved configuration |

Use the `--profile`, `--device`, and `--environment` options to target a specific combination of user, viewport, and deployment:

```bash
invrt test --profile=authenticated --device=mobile --environment=production
```

For full documentation including all commands, options, configuration reference, and examples see **[docs/usage.md](docs/usage.md)**.

---

## Development

Primarily coded using agentic methods. For more information see [AGENTS.md](AGENTS.md).


### Dependencies

PHP 8.5, Composer, Node, npm, playwrite, Taskfile.dev.

For more details see the [Dockerfile](./Dockerfile)

### Testing

```bash
task test
```

### Release Automation

Patch releases are automated by task runner commands:

```bash
task version:bump
```

This command will:
- bump to the next patch version,
- update version references in tracked files,
- run `task test`,
- build and publish Docker images (`:latest` and `:<version>`),
- publish the `ddev-invrt` addon and push a matching git tag.

Required environment variables:

```bash
export DOCKERHUB_USERNAME=your-user
export DOCKERHUB_TOKEN=your-token
export GITHUB_TOKEN=your-github-token
```

