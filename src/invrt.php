#!/usr/bin/env php
<?php

// inVRT CLI - Visual Regression Testing Tool
// Powered by Symfony Console

require_once __DIR__ . '/../vendor/autoload.php';

use App\Commands\ConfigCommand;
use App\Commands\CrawlCommand;
use App\Commands\InitCommand;
use App\Commands\ReferenceCommand;
use App\Commands\TestCommand;
use App\Service\ConfigurationService;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container->autowire(ConfigurationService::class)->setPublic(true);

$container->autowire(CrawlCommand::class)->setPublic(true);
$container->autowire(ReferenceCommand::class)->setPublic(true);
$container->autowire(TestCommand::class)->setPublic(true);
$container->autowire(ConfigCommand::class)->setPublic(true);
$container->autowire(InitCommand::class)->setPublic(true);

$container->compile();

$app = new Application('📖 inVRT CLI - Visual Regression Testing Tool', '1.0.2');

$app->addCommand($container->get(InitCommand::class));
$app->addCommand($container->get(CrawlCommand::class));
$app->addCommand($container->get(ReferenceCommand::class));
$app->addCommand($container->get(TestCommand::class));
$app->addCommand($container->get(ConfigCommand::class));

$app->run();
