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

### Run Tests with Composer

    # Run all tests
    composer test

    # Run only unit tests
    composer test:unit

    # Run only integration tests
    composer test:integration

    # Generate code coverage report
    composer test:coverage

    # Generate HTML code coverage report
    composer test:coverage-html

### Dependencies

Test dependencies are installed with:

    composer install

The test suite requires PHPUnit (installed as a dev dependency) and Symfony YAML.

For more information about the test suite, see [tests/README.md](tests/README.md).

## Run Using Docker

You can run inVRT using the docker container without installing it.

     docker run -it --volume .:/dir ronan4000/invrt init
