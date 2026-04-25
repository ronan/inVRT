#!/usr/bin/env php
<?php

// inVRT CLI - Visual Regression Testing Tool
// Powered by Symfony Console

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Commands\ApproveCommand;
use App\Commands\BaselineCommand;
use App\Commands\CheckCommand;
use App\Commands\ConfigCommand;
use App\Commands\ConfigurePlaywrightCommand;
use App\Commands\CrawlCommand;
use App\Commands\GeneratePlaywrightCommand;
use App\Commands\InfoCommand;
use App\Commands\InitCommand;
use App\Commands\ReferenceCommand;
use App\Commands\TestCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container->autowire(ApproveCommand::class)->setPublic(true);
$container->autowire(BaselineCommand::class)->setPublic(true);
$container->autowire(ConfigurePlaywrightCommand::class)->setPublic(true);
$container->autowire(GeneratePlaywrightCommand::class)->setPublic(true);
$container->autowire(CheckCommand::class)->setPublic(true);
$container->autowire(CrawlCommand::class)->setPublic(true);
$container->autowire(ReferenceCommand::class)->setPublic(true);
$container->autowire(TestCommand::class)->setPublic(true);
$container->autowire(ConfigCommand::class)->setPublic(true);
$container->autowire(InfoCommand::class)->setPublic(true);
$container->autowire(InitCommand::class)->setPublic(true);

$container->compile();

$app = new Application('
                  ◀ █ ▶
                    ❚
░▒▓█▓▒░             ❚ ░██    ░██  ░█████████  ░██████████
                    ❚ ░██    ░██  ░██     ░██     ░██
░▒▓█▓▒░▒▓███████▓▒░ ❚ ░██    ░██  ░██     ░██     ░██
░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░❚ ░██    ░██  ░█████████      ░██
░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░❚  ░██  ░██   ░██   ░██       ░██
░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░❚   ░██░██    ░██    ░██      ░██
░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░❚    ░███     ░██     ░██     ░██
                    ❚
                  ◀ █ ▶', '0.2.0');

$app->addCommand($container->get(InfoCommand::class));
$app->addCommand($container->get(InitCommand::class));
$app->addCommand($container->get(ApproveCommand::class));
$app->addCommand($container->get(BaselineCommand::class));
$app->addCommand($container->get(CheckCommand::class));
$app->addCommand($container->get(ConfigurePlaywrightCommand::class));
$app->addCommand($container->get(GeneratePlaywrightCommand::class));
$app->addCommand($container->get(CrawlCommand::class));
$app->addCommand($container->get(ReferenceCommand::class));
$app->addCommand($container->get(TestCommand::class));
$app->addCommand($container->get(ConfigCommand::class));

$app->run();
