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
use Symfony\Component\Console\Application;

// Create the application
$app = new Application('inVRT CLI', '1.0.0');
$app->setName('📖 inVRT CLI - Visual Regression Testing Tool');

// Register commands
$app->addCommand(new InitCommand());
$app->addCommand(new CrawlCommand());
$app->addCommand(new ReferenceCommand());
$app->addCommand(new TestCommand());
$app->addCommand(new ConfigCommand());

// Run the application
$app->run();
