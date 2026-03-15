# inVRT PHP Port

This is a PHP port of the inVRT CLI tool originally written in Node.js. Two versions are provided to support different deployment scenarios.

## Overview

The PHP version maintains feature parity with the JavaScript original, including:
- Command parsing (init, crawl, reference, test)
- Configuration management via YAML
- Profile and environment-specific settings
- Authentication credential handling
- Cookie conversion to wget/curl format
- Environment variable setup and bash script execution

## Two Versions Available

### Version 1: `invrt.php` (Using Composer/Symfony)
**Recommended for most users**
- Uses Symfony YAML component via Composer
- No system-level YAML dependencies required
- Works with any PHP version 7.4+
- Easier to deploy and manage

### Version 2: `invrt-alt.php` (Using PHP YAML Extension)
**Lightweight alternative**
- Uses PHP's native YAML extension
- No composer dependencies needed
- Smaller footprint, faster startup
- Requires `php-yaml` extension to be installed

## Installation

### Prerequisites
- PHP 7.4 or higher
- Bash shell for executing sub-scripts

### Option A: Using Composer (Recommended)

```bash
# Install dependencies
composer install

# Make the script executable (optional)
chmod +x src/invrt.php
```

### Option B: Using PHP YAML Extension

**Linux (Debian/Ubuntu):**
```bash
sudo apt-get install php-yaml
chmod +x src/invrt-alt.php
```

**macOS (Homebrew):**
```bash
brew install php-yaml
chmod +x src/invrt-alt.php
```

**Other systems:**
See [PHP YAML Extension Installation](https://www.php.net/manual/en/yaml.installation.php)

## Usage

### Using Version 1 (invrt.php with Composer)

Using Composer scripts:
```bash
# Initialize a new project
composer run init

# Crawl a website
composer run crawl -- --profile=default --device=desktop

# Create reference screenshots
composer run reference -- --profile=default --environment=staging

# Run visual regression tests
composer run test
```

Direct execution:
```bash
# Initialize
php src/invrt.php init

# Crawl with options
php src/invrt.php crawl --profile=default --device=desktop

# With environment
php src/invrt.php crawl -p sponsor -d mobile -e dev

# Show help
php src/invrt.php help
```

### Using Version 2 (invrt-alt.php with PHP YAML Extension)

```bash
# Initialize
php src/invrt-alt.php init

# Crawl with options
php src/invrt-alt.php crawl --profile=default --device=desktop

# With environment
php src/invrt-alt.php crawl -p sponsor -d mobile -e dev

# Show help
php src/invrt-alt.php help
```

### Using Composer Scripts with Alternative Version

Update `composer.json` to use the alternative version:
```json
{
    "scripts": {
        "init": ["php src/invrt-alt.php init"],
        "crawl": ["php src/invrt-alt.php crawl"],
        "reference": ["php src/invrt-alt.php reference"],
        "test": ["php src/invrt-alt.php test"]
    }
}
```

## Comparison: Which Version to Use?

| Feature | invrt.php | invrt-alt.php |
|---------|-----------|---------------|
| Dependencies | Composer | PHP YAML extension |
| Installation | `composer install` | `apt-get install php-yaml` |
| File Size | Smaller (no vendor/) | Very small |
| Startup Time | Slightly slower | Slightly faster |
| Portability | Better (pure PHP) | Good (needs YAML ext) |
| Easy Deployment | ✓ Yes | ✓ Yes for PHP projects |
| Docker Friendly | ✓ Yes | ✓ With YAML extension |
| Recommended For | Most users | Lightweight setups |

### Choose `invrt.php` if:
- You're using Composer in your project
- You want no system-level dependencies
- You deploy via Docker and want consistency
- You prefer dependency management via composer.json

### Choose `invrt-alt.php` if:
- You have PHP YAML extension already installed
- You want minimal footprint
- You're in a lightweight/embedded environment
- You don't use Composer in your project

## Command-Line Options

- `--profile, -p <name>` - Set the device profile (default: default)
- `--device, -d <name>` - Set the device type (default: desktop)
- `--environment, -e <name>` - Set the environment (dev, staging, prod, etc.)
- `--help, -h` - Show help message

## Configuration

The tool reads configuration from `.invrt/config.yaml`. The structure mirrors the JavaScript version:

```yaml
project:
  url: https://example.com

settings:
  max_crawl_depth: 3
  max_pages: 100
  user_agent: "Mozilla/5.0..."
  max_concurrent_requests: 5

profiles:
  default:
    url: https://example.com
    max_crawl_depth: 3
    auth:
      username: user@example.com
      password: password
  
  mobile:
    device: mobile
    max_pages: 50

environments:
  dev:
    url: https://dev.example.com
  staging:
    url: https://staging.example.com
  prod:
    url: https://example.com
```

## Key Differences from JavaScript Version

1. **Dependencies**: Uses `symfony/yaml` instead of `js-yaml` for YAML parsing
2. **Process Execution**: Uses PHP's `passthru()` instead of Node.js `spawn()`
3. **Authentication**: Currently delegates to the Node.js Playwright script since PHP doesn't have native Playwright support. This requires Node.js to be available on the system.
4. **Async**: PHP doesn't have built-in async/await, so the login function is synchronous
5. **Path Handling**: Uses `DIRECTORY_SEPARATOR` for cross-platform compatibility
6. **Environment Variables**: Passes variables to subprocesses via the command environment

## Authentication

The tool supports authentication through username/password combinations defined in profiles or environments. When credentials are provided, it will:

1. Attempt to log in using Playwright (via the Node.js script)
2. Save cookies to `cookies.json`
3. Convert cookies to wget/curl Netscape format as `.txt`

## Data Directory Structure

```
.invrt/
├── config.yaml
├── data/
│   └── {profile}/
│       └── {environment}/
│           ├── crawled_urls.txt
│           ├── logs/
│           │   ├── crawl.log
│           │   └── crawl_error.log
│           └── cookies.json
```

## Troubleshooting

### For invrt.php (Composer Version)

**Missing composer dependencies**
```bash
composer install
```

**Autoloader not loading**
Make sure you're running from the project root where composer.json exists.

### For invrt-alt.php (YAML Extension Version)

**YAML extension not found**
```
❌ PHP YAML extension is required but not installed.
```
Install the extension for your system (see Installation section above)

**Verify YAML extension is installed**
```bash
php -m | grep yaml
```

### Both Versions

**YAML parsing errors**
Ensure `config.yaml` is valid YAML syntax. Test with:
```bash
php -r "var_dump(yaml_parse_file('.invrt/config.yaml'));"
```

**Authentication fails**
- Ensure Node.js and Playwright are installed (`node -v`)
- Check that credentials are correct in `config.yaml`
- Verify the login URL is correct

**Script permissions**
Make sure bash scripts are executable:
```bash
chmod +x src/*.sh
```

**Environment variables not set**
Verify variables are being exported to subprocesses:
```bash
php src/invrt.php crawl 2>&1 | head -20
```

## Implementing Your Own YAML Parser

If you want to use a different YAML library, both scripts have YAML parsing logic in these sections:

**invrt.php (Composer version):**
- Lines 118-123: YAML parsing using Symfony\Yaml

**invrt-alt.php (Extension version):**
- Lines 85-88: YAML parsing using yaml_parse_file()

## Docker Usage

### Using invrt.php in Docker

**Dockerfile example:**
```dockerfile
FROM php:8.1-cli

# Install required extensions
RUN apt-get update && apt-get install -y \
    git \
    bash \
    curl \
    && docker-php-ext-install -j$(nproc) \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev

ENTRYPOINT ["php", "src/invrt.php"]
```

Build and run:
```bash
docker build -t invrt-php .
docker run invrt-php crawl --profile=default
```

### Using invrt-alt.php in Docker

**Dockerfile example:**
```dockerfile
FROM php:8.1-cli

# Install required extensions including YAML
RUN apt-get update && apt-get install -y \
    git \
    bash \
    curl \
    php-yaml \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .

ENTRYPOINT ["php", "src/invrt-alt.php"]
```

## Quick Reference Guide

### Initialization
```bash
# Version 1 (Composer)
php src/invrt.php init

# Version 2 (YAML Extension)
php src/invrt-alt.php init
```

### Crawling with Different Profiles
```bash
# Desktop profile
php src/invrt.php crawl -p default -d desktop

# Mobile profile
php src/invrt.php crawl -p mobile -d mobile

# Custom profile with environment
php src/invrt.php crawl -p sponsor -d mobile -e production
```

### Long Form vs Short Form Options
```bash
# These are equivalent:
php src/invrt.php crawl --profile=default --device=desktop --environment=dev
php src/invrt.php crawl -p default -d desktop -e dev
```

### Creating Reference Screenshots
```bash
php src/invrt.php reference -p default -e staging
```

### Running Tests
```bash
php src/invrt.php test -p default
```

### Getting Help
```bash
php src/invrt.php help
php src/invrt.php --help
php src/invrt.php -h
```

## Migration from Node.js Version

To migrate from the Node.js version:

1. Install composer dependencies: `composer install`
2. Replace `node src/invrt.js` calls with `php src/invrt.php`
3. Update any CI/CD pipelines to use the PHP version
4. No changes needed to `config.yaml` - it's fully compatible

## Performance Notes

The PHP version performs identically to the JavaScript version since both delegate the actual work to the same bash scripts. The difference is purely in the CLI wrapper and configuration management.

## License

MIT - See LICENSE file for details
