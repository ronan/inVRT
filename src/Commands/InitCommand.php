<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'init',
    description: 'Initialize a new inVRT project in the current directory',
    help: 'Initializes a new inVRT project with the default configuration structure.',
)]
class InitCommand extends BaseCommand
{
    // Doesn't matter if we don't call boot
    protected bool $requiresConfig = false;
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        $this->boot($opts, $io);

        [
            $INVRT_CWD,
            $INVRT_DIRECTORY,
            $INVRT_CONFIG_FILE,
        ] = array_fill(0, 5, '');
        extract($this->config, EXTR_IF_EXISTS);

        if (empty($INVRT_CWD)) {
            $io->error("⚠️  I can't make a directory here because I don't know where I am.");
            return Command::FAILURE;
        }

        if (is_dir($INVRT_DIRECTORY)) {
            $io->error('⚠️  InVRT is already initialized for this project. Please remove the .invrt directory (' . $INVRT_DIRECTORY . ') if you want to re-initialize.');
            return Command::FAILURE;
        }

        $io->writeln('🚀 Initializing InVRT for the project at ' . $INVRT_CWD);

        if (!mkdir($INVRT_DIRECTORY, 0755, true)) {
            $io->error('Failed to create invrt directory at ' . $INVRT_DIRECTORY);
            return Command::FAILURE;
        }
        $io->writeln('<info>✓ Created invrt directory at ' . $INVRT_DIRECTORY . '</info>', OutputInterface::VERBOSITY_VERBOSE);

        if (!mkdir(Path::join($INVRT_DIRECTORY, 'data'), 0755, true)) {
            $io->error('Failed to create data directory');
            return Command::FAILURE;
        }

        if (!mkdir(Path::join($INVRT_DIRECTORY, 'scripts'), 0755, true)) {
            $io->error('Failed to create scripts directory');
            return Command::FAILURE;
        }
        $io->writeln('<info>✓ Created data directories for generated data, and user scripts.</info>', OutputInterface::VERBOSITY_VERBOSE);

        $configContent = <<<'YAML'
# InVRT Configuration File
# This file is used to store configuration settings for InVRT.
# You can customize the settings below as needed.

name: My InVRT Project

environments:
  local:
    name: Local
    url: http://localhost

  dev:
    name: Development
    url: https://dev.example.com

  prod:
    name: Production
    url: https://prod.example.com

profiles:
  anonymous:
    name: Anonymous Visitor Profile
    description: Test the site as an anonymous visitor with no special permissions.

  admin:
    name: Admin Profile
    description: A profile with admin privileges.
    username: admin
    password: password123

devices:
  desktop:
    name: Desktop Viewport
    description: A desktop sized viewport for testing.
    viewport_width: 1920
    viewport_height: 1080

  mobile:
    name: Mobile Viewport
    description: A viewport for mobile testing.
    viewport_width: 375
    viewport_height: 667
YAML;

        if (file_put_contents($INVRT_CONFIG_FILE, $configContent) === false) {
            $io->error('Failed to create config.yaml');
            return Command::FAILURE;
        }
        $io->writeln('<info>✓ Initialized InVRT configuration file at ' . $INVRT_CONFIG_FILE . '</info>', OutputInterface::VERBOSITY_VERBOSE);

        $excludeUrls = "/user/logout\n/files\n/sites\n/core\n";
        $excludePath = Path::join($INVRT_DIRECTORY, 'exclude_urls.txt');
        if (file_put_contents($excludePath, $excludeUrls) === false) {
            $io->error('Failed to create exclude_urls.txt');
            return Command::FAILURE;
        }

        $io->success('InVRT successfully initialized!');
        return Command::SUCCESS;
    }
}
