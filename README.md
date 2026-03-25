# inVRT

A web VRT tool to continuously check for visual changes in a website or web app.

### Usage:

    $ invrt init

    $ invrt crawl

    $ invert test

### Options:

#### --profile

Select one or more user profiles to test with. The 'default' profile is an anonymous user. Separate multiple profiles with a comma (,). Regex expressions are accepted. Defaults to '.*' or all profiles.

    invrt crawl --profile=default
    invrt crawl --profile=author,admin,robot
    invrt test  --profile=.*

#### --device

Select one or more simulated devices to test with. The default viewport is a desktop browser with a 1920x1080 resolution. This affects the window capture size only. Tests are run in the desktop version of Chrome.

    invert test --device=desktop
    invert test --device=mobile,desktop

#### --environments

The instance of the project under test.

    invrt crawl --environment=local
    invrt test --environment=prod,stage

## Testing

The project includes a PHPUnit test suite covering utility functions, CLI argument parsing, and configuration handling.

### Run Tests with Task

    # Run all tests
    task test

    # Run only unit tests
    task test:unit

    # Run only E2E tests
    task test:e2e

    # Generate code coverage report
    task test:coverage

    # Generate HTML code coverage report
    task test:coverage-html

### Dependencies

Test dependencies are installed with:

    composer install

The test suite requires PHPUnit (installed as a dev dependency) and Symfony YAML.

For more information about the test suite, see [tests/README.md](tests/README.md).

## Static Analysis

The project uses PHPStan for static code analysis to catch potential bugs and type errors.

### Run Static Analysis

    # Analyze code
    task analyze

    # Generate baseline for tracking error changes
    task analyze:baseline

The analysis runs at level 5 (strict) and checks the `src/` directory. Configuration is in `phpstan.neon`.

## Run Using Docker

You can run inVRT using the docker container without installing it.

     docker run -it --volume .:/dir ronan4000/invrt init
