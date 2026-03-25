<?php

namespace App\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use App\Service\EnvironmentService;

class ConfigCommand extends BaseCommand
{
    protected static $defaultName = 'config';
    protected static $defaultDescription = 'View the inVRT configuration';

    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('Displays the current inVRT project configuration.');
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
        $configFile = $this->joinPath($_ENV['INVRT_DIRECTORY'], 'config.yaml');

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
            
            // Display the configuration in a readable format (it's Yaml)
            foreach ($config as $section => $values) {
                $output->write($section . ':');
                if (is_array($values)) {
                    $output->writeln('');
                    foreach ($values as $key => $value) {
                        if (is_array($value)) {
                            $output->writeln('  ' . $key . ':');
                            foreach ($value as $subkey => $subvalue) {
                                $outputValue = is_array($subvalue) ? json_encode($subvalue) : $subvalue;
                                $output->writeln('    ' . $subkey . ': ' . $outputValue);
                            }
                        } else {
                            $output->writeln('  ' . $key . ': ' . $value);
                        }
                    }
                }
                $output->writeln('');
            }
        } catch (\Exception $error) {
            $output->writeln('<error>Error reading config file: ' . $error->getMessage() . '</error>');
            return 1;
        }

        return 0;
    }

    protected function getScriptName(): string
    {
        // Config command doesn't execute a script
        return '';
    }
}
