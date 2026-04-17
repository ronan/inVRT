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
        $io->text($data['config_file']);
        $io->newLine();

        $io->definitionList(
            ['Environment' => $data['environment']],
            ['Profile'     => $data['profile']],
            ['Device'      => $data['device']],
        );

        $io->definitionList(
            ['Environments' => implode(', ', $data['environments']) ?: '(none)'],
            ['Profiles'     => implode(', ', $data['profiles'])     ?: '(none)'],
            ['Devices'      => implode(', ', $data['devices'])      ?: '(none)'],
        );

        $io->definitionList(
            ['Crawled pages'           => (string) $data['crawled_pages']],
            ['Reference screenshots'   => (string) $data['reference_screenshots']],
            ['Test screenshots'        => (string) $data['test_screenshots']],
        );

        if (!empty($data['crawl_log_tail'])) {
            $io->section('Crawl log (last 5 lines)');
            foreach ($data['crawl_log_tail'] as $line) {
                $io->text($line);
            }
        }

        return Command::SUCCESS;
    }
}
