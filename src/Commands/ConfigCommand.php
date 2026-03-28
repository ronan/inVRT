<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'config',
    description: 'View the inVRT configuration',
    help: 'Displays the current inVRT project configuration.',
)]
class ConfigCommand extends BaseCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        return $this->withEnv($opts, $io, function (array $env) use ($io): int {
            $configFile = Path::join($env['INVRT_DIRECTORY'], 'config.yaml');

            if (!file_exists($configFile)) {
                $io->writeln('# Configuration file not found at: ' . $configFile);
                $io->writeln("# Run '<comment>invrt init</comment>' to create a new configuration.");
                return Command::SUCCESS;
            }

            try {
                $fileContents = file_get_contents($configFile);
                $config = Yaml::parse($fileContents) ?: [];

                $io->writeln('# Current inVRT Configuration:');
                $io->writeln('# ============================');
                $io->writeln('');

                $this->displayConfiguration($config, $io);
            } catch (\Exception $error) {
                $io->error('Error reading config file: ' . $error->getMessage());
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        }, requiresConfig: false);
    }

    private function displayConfiguration(array $config, SymfonyStyle $io): void
    {
        foreach ($config as $section => $values) {
            if (!is_array($values)) {
                $io->writeln($section . ': ' . $values);
                continue;
            }

            $io->writeln($section . ':');
            foreach ($values as $key => $value) {
                $this->displayConfigValue($key, $value, $io);
            }
            $io->writeln('');
        }
    }

    private function displayConfigValue(string $key, mixed $value, SymfonyStyle $io): void
    {
        if (is_array($value)) {
            $io->writeln('  ' . $key . ':');
            foreach ($value as $subkey => $subvalue) {
                $formatted = is_array($subvalue) ? json_encode($subvalue) : $subvalue;
                $io->writeln('    ' . $subkey . ': ' . $formatted);
            }
        } else {
            $io->writeln('  ' . $key . ': ' . $value);
        }
    }
}
