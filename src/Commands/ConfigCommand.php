<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'config',
    description: 'View the inVRT configuration',
    help: 'Displays the current inVRT project configuration.',
)]
class ConfigCommand extends BaseCommand
{
    protected bool $requiresConfig = true;
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        $this->displayConfiguration($this->config, $io, 'Current inVRT Configuration');

        return Command::SUCCESS;
    }

    private function displayConfiguration(array $config, SymfonyStyle $io, $title = ''): void
    {
        if ($title !== '') {
            $io->writeln("# $title:");
            $io->writeln('');
        }

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
