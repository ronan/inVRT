<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize a new inVRT project in the current directory')
            ->setHelp('Initializes a new inVRT project with the default configuration structure.');
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $initCwd = getenv('INIT_CWD') ?: getcwd();
        $invrtDirectory = $this->joinPath($initCwd, '.invrt');

        $output->writeln('🚀 Initializing InVRT for the project at ' . $initCwd);

        // Check if already initialized
        if (is_dir($invrtDirectory)) {
            $output->writeln('<error>⚠️  InVRT is already initialized for this project. Please remove the .invrt directory if you want to re-initialize.</error>');
            return Command::FAILURE;
        }

        // Create .invrt directory and subdirectories
        if (!mkdir($invrtDirectory, 0755, true)) {
            $output->writeln('<error>Failed to create invrt directory at ' . $invrtDirectory . '</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>✓ Created invrt directory at ' . $invrtDirectory . '</info>');

        if (!mkdir($this->joinPath($invrtDirectory, 'data'), 0755, true)) {
            $output->writeln('<error>Failed to create data directory</error>');
            return Command::FAILURE;
        }

        if (!mkdir($this->joinPath($invrtDirectory, 'scripts'), 0755, true)) {
            $output->writeln('<error>Failed to create scripts directory</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>✓ Created data directories for generated data, and user scripts.</info>');

        // Create config.yaml
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

        $configPath = $this->joinPath($invrtDirectory, 'config.yaml');
        if (file_put_contents($configPath, $configContent) === false) {
            $output->writeln('<error>Failed to create config.yaml</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>✓ Initialized InVRT configuration file at ' . $configPath . '</info>');

        // Create exclude_urls.txt
        $excludeUrls = "/user/logout\n/files\n/sites\n/core\n";
        $excludePath = $this->joinPath($invrtDirectory, 'exclude_urls.txt');
        if (file_put_contents($excludePath, $excludeUrls) === false) {
            $output->writeln('<error>Failed to create exclude_urls.txt</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>🚀 InVRT successfully initialized!</info>');
        return Command::SUCCESS;
    }

    /**
     * Helper method to join path segments
     */
    private function joinPath(string ...$parts): string
    {
        return implode(DIRECTORY_SEPARATOR, array_filter($parts));
    }
}
