<?php

namespace App\Commands;

use App\Service\EnvironmentService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ConfigCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('config')
            ->setDescription('View the inVRT configuration')
            ->setHelp('Displays the current inVRT project configuration.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $input->getOption('profile');
        $device = $input->getOption('device');
        $environment = $input->getOption('environment');

        // Initialize environment service - config file not required for viewing
        $this->environment = new EnvironmentService($profile, $device, $environment);
        $this->environment->initialize($output, false);

        // Get config file path
        $configFile = $this->joinPath(getenv('INVRT_DIRECTORY') ?: '.invrt', 'config.yaml');

        // Check if config file exists
        if (!file_exists($configFile)) {
            $output->writeln('# Configuration file not found at: ' . $configFile);
            $output->writeln("# Run '<comment>invrt init</comment>' to create a new configuration.");
            return 0;
        }

        try {
            $fileContents = file_get_contents($configFile);
            $config = Yaml::parse($fileContents) ?: [];

            $output->writeln('# Current inVRT Configuration:');
            $output->writeln('# ============================');
            $output->writeln('');

            $this->displayConfiguration($config, $output);
        } catch (\Exception $error) {
            $output->writeln('<error>Error reading config file: ' . $error->getMessage() . '</error>');
            return 1;
        }

        return 0;
    }

    /**
     * Display configuration sections in readable format
     */
    private function displayConfiguration(array $config, OutputInterface $output): void
    {
        foreach ($config as $section => $values) {
            if (!is_array($values)) {
                $output->writeln($section . ': ' . $values);
                continue;
            }

            $output->writeln($section . ':');
            foreach ($values as $key => $value) {
                $this->displayConfigValue($key, $value, $output);
            }
            $output->writeln('');
        }
    }

    /**
     * Display a single config value
     */
    private function displayConfigValue(string $key, mixed $value, OutputInterface $output): void
    {
        if (is_array($value)) {
            $output->writeln('  ' . $key . ':');
            foreach ($value as $subkey => $subvalue) {
                $formatted = is_array($subvalue) ? json_encode($subvalue) : $subvalue;
                $output->writeln('    ' . $subkey . ': ' . $formatted);
            }
        } else {
            $output->writeln('  ' . $key . ': ' . $value);
        }
    }

    protected function getScriptName(): string
    {
        // Config command doesn't execute a script
        return '';
    }
}
