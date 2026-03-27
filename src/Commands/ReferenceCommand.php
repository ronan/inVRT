<?php

namespace App\Commands;

use App\Service\EnvironmentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReferenceCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('reference')
            ->setDescription('Create reference screenshots for comparison')
            ->setHelp('Creates reference screenshots for the specified profile, device, and environment to use as baseline for comparison.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->environment = new EnvironmentService(
            $input->getOption('profile'),
            $input->getOption('device'),
            $input->getOption('environment'),
        );
        $env = $this->environment->initialize($output, true);

        $loginResult = $this->handleLogin($output, $env);
        if ($loginResult !== Command::SUCCESS) {
            return $loginResult;
        }

        $output->writeln(
            "📸 Capturing references from '{$env['INVRT_ENVIRONMENT']}' environment ({$env['INVRT_URL']}) with profile: '{$env['INVRT_PROFILE']}' and device: '{$env['INVRT_DEVICE']}'",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        $exitCode = null;
        passthru('node ' . escapeshellarg($env['INVRT_SCRIPTS_DIR'] . '/backstop.js') . ' reference', $exitCode);

        return $exitCode ?? Command::SUCCESS;
    }

    protected function getScriptName(): string
    {
        return '';
    }
}
