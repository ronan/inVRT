<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'info',
    description: 'Show project status summary',
    help: 'Displays the project name, configured environments/profiles/devices, and current crawl/screenshot counts.',
)]
class InfoCommand extends BaseCommand
{
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        $data = $this->runner->info();

        $io->title($data['name'] ?: 'inVRT Project');
        $io->text($data['plan_file'] ?? '');
        $io->newLine();

        $io->definitionList(
            ['Project ID'  => $data['id'] ?: '(not set)'],
            ['Environment' => $data['environment']],
            ['Profile'     => $data['profile']],
            ['Device'      => $data['device']],
        );

        $io->definitionList(
            ['Environments' => implode(', ', $data['environments']) ?: '(none)'],
            ['Profiles'     => implode(', ', $data['profiles']) ?: '(none)'],
            ['Devices'      => implode(', ', $data['devices']) ?: '(none)'],
        );

        $io->definitionList(
            ['Planned pages'           => (string) ($data['planned_pages'] ?? 0)],
            ['Reference screenshots'   => (string) ($data['reference_screenshots'] ?? 0)],
            ['Test screenshots'        => (string) ($data['test_screenshots'] ?? 0)],
        );

        return Command::SUCCESS;
    }
}
