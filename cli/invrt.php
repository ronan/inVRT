#!/usr/bin/env php
<?php

// inVRT CLI - Visual Regression Testing Tool
// Powered by Symfony Console

require_once __DIR__ . '/../vendor/autoload.php';

use App\Commands\ApproveCommand;
use App\Commands\BaselineCommand;
use App\Commands\ConfigCommand;
use App\Commands\CrawlCommand;
use App\Commands\InitCommand;
use App\Commands\ReferenceCommand;
use App\Commands\TestCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container->autowire(ApproveCommand::class)->setPublic(true);
$container->autowire(BaselineCommand::class)->setPublic(true);
$container->autowire(CrawlCommand::class)->setPublic(true);
$container->autowire(ReferenceCommand::class)->setPublic(true);
$container->autowire(TestCommand::class)->setPublic(true);
$container->autowire(ConfigCommand::class)->setPublic(true);
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
                  ◀ █ ▶', '0.1.1');

$app->addCommand($container->get(InitCommand::class));
$app->addCommand($container->get(ApproveCommand::class));
$app->addCommand($container->get(BaselineCommand::class));
$app->addCommand($container->get(CrawlCommand::class));
$app->addCommand($container->get(ReferenceCommand::class));
$app->addCommand($container->get(TestCommand::class));
$app->addCommand($container->get(ConfigCommand::class));

$app->run();
