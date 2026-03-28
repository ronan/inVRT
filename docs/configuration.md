# InVRT Configuration

## Config File

Configuration for a given project is stored in a yaml file the INVRT_DIRECTORY:

    `{CWD}/.invrt/config.yml`

### Example config.yml File

```yaml
# .invrt/config.yaml

name: My inVRT Project

settings:
  project_type: drupal11
  viewport_width: 1920
  viewport_height: 1080
  max_crawl_depth: 3
  max_pages: 100
  max_concurrent_requests: 5
  login_url: user/login
  user_agent: InVRT/1.0

environments:
  local:
    url: http://localhost

  stage:
    url: https://dev.example.com
     max_pages: 20

  prod:
    url: https://prod.example.com

profiles:
  anonymous:
    description: Test the site as an anonymous visitor with no special permissions.

  admin:
    description: A profile with admin privileges.
    username: admin
    password: password123
    login_url: user/login
    max_pages: 50

devices:
  desktop:
    viewport_width: 1920
    viewport_height: 1080

  mobile:
    viewport_width: 375
    viewport_height: 667
    max_pages: 90

```

## Config Sections

The config file contains the following named sections:

| Name        | Yaml Section   | Option                | Default   | Represents                                        |
| ----------- | -------------- | --------------------- | --------- | ------------------------------------------------- |
| Settings    | `settings`     | _n/a_                 | _n/a_     | Base values for the project                       |
| Environment | `environments` | `--environment={key}` | local     | Installations (local, stage, live) of the project |
| Profile     | `profiles`     | `--profile={key}`     | anonymous | User personas to test with                        |
| Device      | `devices`      | `--device={key}`      | desktop   | Screen sizes to capture                           |

Values in the 'settings' section will be overriden by values in the Environment, Profile and Device specified by the command options when the tool is run.


### Environments

Environments represent different deployments of the same site (local, dev, staging, prod). Each typically sets a different `url`.

```yaml
environments:
  local:
    url: http://localhost

  staging:
    url: https://staging.example.com
    username: ci_user
    password: ci_pass

  prod:
    url: https://www.example.com
```

Select with `--environment`:

```bash
invrt crawl --environment=staging
invrt test  --environment=prod
```

### Profiles

Profiles represent different user states — anonymous visitors, editors, admins, etc. A profile with `username` and `password` triggers an authenticated browser session before crawling and screenshotting.

```yaml
profiles:
  anonymous:
    name: Anonymous Visitor

  editor:
    name: Content Editor
    username: editor
    password: editorpass

  admin:
    name: Site Administrator
    username: admin
    password: adminpass
```

Select with `--profile`:

```bash
invrt crawl --profile=editor
invrt test  --profile=admin
```

### Devices

Devices set the browser viewport size and user agent, letting you test responsive layouts.

```yaml
devices:
  desktop:
    viewport_width: 1920
    viewport_height: 1080

  tablet:
    viewport_width: 768
    viewport_height: 1024

  mobile:
    viewport_width: 375
    viewport_height: 667
    user_agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)
```

Select with `--device`:

```bash
invrt reference --device=mobile
invrt test      --device=mobile
```

The value of a config setting is set in the following order with the default value having the lowest precedence and the currently set environment variable having the highest:

    Default Value
        -> Project Setting Value
            -> Specified Environment Value
                ->Specified Profile Value
                    ->Specified Device Value
                        -> Environmental Variable

The example config file above would produce the following results:

```bash

> invrt config | grep max_pages
max_pages: 100

> invrt config --environment=stage | grep max_pages
max_pages: 20

> invrt config --profile=admin | grep max_pages
max_pages: 50

> invrt config --device=mobile | grep max_pages
max_pages: 90

> invrt config  --environment=stage --profile=admin --device=mobile | grep max_pages
max_pages: 90

```



## Configuration Options Reference

| Config Key        | Environment Variable     | Default Value                  | Section      | Commands Affected      | Description                                |
| ----------------- | ------------------------ | ------------------------------ | ------------ | ---------------------- | ------------------------------------------ |
| `cwd`             | `$INIT_CWD`              | './'                           | -            | all                    | Working directory where invrt was invoked  |
| `directory`       | `$INVRT_DIRECTORY`       | `{INIT_CWD}/.invrt`            | -            | all                    | Path to `.invrt/` directory                |
| `config_file`     | `$INVRT_CONFIG_FILE`     | `{INVRT_DIRECTORY}/config.yml` | -            | all                    | Path to `config.yaml`                      |
| `data_dir`        | `$INVRT_DATA_DIR`        | _(varies)_                     | -            | all                    | `.invrt/data/<profile>/<env>/`             |
| `cookies_file`    | `$INVRT_COOKIES_FILE`    | _(varies)_                     | -            | all                    | `.invrt/data/<profile>/<env>/cookies`      |
| `scripts_dir`     | `$INVRT_SCRIPTS_DIR`     | _(varies)_                     | -            | all                    | Path to the user scripts                   |
| `url`             | `$INVRT_URL`             | _(empty)_                      | environments | crawl, reference, test | Base URL to crawl and test                 |
| `login_url`       | `$INVRT_LOGIN_URL`       | `{INVRT_URL}/user/login`       | profiles     | crawl, reference, test | Login page URL                             |
| `username`        | `$INVRT_USERNAME`        | _(empty)_                      | profiles     | crawl, reference, test | Login username                             |
| `password`        | `$INVRT_PASSWORD`        | _(empty)_                      | profiles     | crawl, reference, test | Login password                             |
| `viewport_width`  | `$INVRT_VIEWPORT_WIDTH`  | `1024`                         | devices      | reference, test        | Browser viewport width in pixels           |
| `viewport_height` | `$INVRT_VIEWPORT_HEIGHT` | `768`                          | devices      | reference, test        | Browser viewport height in pixels          |
| `max_crawl_depth` | `$INVRT_MAX_CRAWL_DEPTH` | `3`                            | settings     | crawl                  | Recursion depth for wget crawl             |
| `max_pages`       | `$INVRT_MAX_PAGES`       | `100`                          | settings     | crawl                  | Maximum number of pages to crawl           |
| `user_agent`      | `$INVRT_USER_AGENT`      | `InVRT/1.0`                    | settings     | crawl, reference, test | HTTP User-Agent header sent by the crawler |

For usage see [InVRT Usage](./usage.md)