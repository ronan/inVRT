<?php

namespace App\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\EnvironmentService;

class ConfigCommand extends BaseCommand
{
    protected static $defaultName = 'config';
    protected static $defaultDescription = 'View or modify inVRT configuration';

    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('Allows viewing and modifying the inVRT project configuration.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $input->getOption('profile');
        $device = $input->getOption('device');
        $environment = $input->getOption('environment');

        // Initialize environment service - config file not required for viewing
        $this->environment = new EnvironmentService($profile, $device, $environment);
        $this->environment->initialize($output, false);

        include __DIR__ . '/../invrt-config.php';
        return 0;
    }

    protected function getScriptName(): string
    {
        // Config command doesn't execute a script
        return '';
    }
}
