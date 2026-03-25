# PHPUnit Tests for invrt.php

This directory contains comprehensive PHPUnit tests for the inVRT Visual Regression Testing Tool (PHP version).

## Test Structure

The test suite is organized into two main categories:

### Unit Tests (`tests/Unit`)


- **InvrtCliTest.php**: Tests for the main CLI script logic
  - YAML configuration file parsing
  - Configuration parsing with profiles and environments
  - Environment variable handling
  - Directory and file path construction

## Test Fixtures

Fixture files are located in `tests/fixtures/`:

- **config.yaml**: Full configuration with profiles and environments
- **config-minimal.yaml**: Minimal configuration for testing defaults
- **cookies.json**: Sample cookies for testing conversion

## Running the Tests

### Run all tests:
```bash
php vendor/bin/phpunit
```

### Run with test output format:
```bash
php vendor/bin/phpunit --testdox
```

### Run a specific test file:
```bash
php vendor/bin/phpunit tests/Unit/InvrtUtilsTest.php
```

### Run with code coverage:
```bash
php vendor/bin/phpunit --coverage-text
```

### Run with HTML code coverage report:
```bash
php vendor/bin/phpunit --coverage-html=coverage/
```

## Test Coverage

The current test suite covers:

- **52 total tests** with **126 assertions**
- Utility function behavior and edge cases
- Configuration file parsing and validation
- Argument parsing with various formats
- Profile and environment override behavior
- Cookie conversion functionality
- Error handling and graceful degradation

## Key Test Scenarios

### Configuration Testing

Tests verify that:
- Base configuration values are correctly loaded
- Profile-specific settings override base settings
- Environment-specific settings override profile settings
- Auth credentials are properly extracted
- Missing optional settings use defaults
- Invalid configurations are handled appropriately

### Argument Parsing Testing

Tests verify that:
- Long-form arguments (`--profile=value`) are parsed correctly
- Short-form arguments (`-p=value`) are parsed correctly
- Multiple arguments can be combined
- Default values are preserved when no arguments provided
- Help commands are recognized
- Valid commands are validated

### Cookie Conversion Testing

Tests verify that:
- JSON cookies are converted to Netscape format
- Missing cookie file is handled gracefully
- Invalid JSON is handled without errors
- Optional cookie fields use appropriate defaults
- Output file is properly formatted

## Dependencies

The test suite requires:

- **PHPUnit 9.5 or 10.0**: testing framework
- **Symfony YAML 5.4 or 6.0**: configuration file parsing
- **PHP 7.4+**: as specified in composer.json

Install dependencies with:
```bash
composer install
```

## Continuous Integration

The phpunit.xml configuration file includes:

- Test suite definitions for Unit and E2E tests
- Code coverage configuration (excluding vendor and test directories)
- Display error settings for debugging

## Contributing Tests

When adding new tests:

1. Follow the existing test structure and naming conventions
2. Use descriptive test method names that explain what is being tested
3. Include docblocks explaining the test purpose
4. Use fixture files for data-driven tests
5. Clean up temporary files in tearDown() method
6. Ensure tests are isolated and can run in any order
7. Aim for meaningful assertions, not just code coverage

## Troubleshooting

### Tests fail with "Could not open input file"
- Run `composer install` first to install PHPUnit

### Tests fail with missing classes
- Ensure all required files are in the correct location
- Check that `vendor/autoload.php` exists

### Deprecation warnings
- These are usually harmless warnings from dependencies
- They don't affect test functionality
- Update dependencies if necessary: `composer update`

## License

These tests are part of the inVRT project and follow the same MIT license.
