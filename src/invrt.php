#!/usr/bin/env php
<?php
// inVRT CLI - Visual Regression Testing Tool
// Powered by Symfony Console

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/invrt-utils.inc.php';

use Symfony\Component\Console\Application;
use App\Commands\InitCommand;
use App\Commands\CrawlCommand;
use App\Commands\ReferenceCommand;
use App\Commands\TestCommand;
use App\Commands\ConfigCommand;

// Create the application
$app = new Application('inVRT CLI', '1.0.0');
$app->setName('📖 inVRT CLI - Visual Regression Testing Tool');

// Register commands
$app->add(new InitCommand());
$app->add(new CrawlCommand());
$app->add(new ReferenceCommand());
$app->add(new TestCommand());
$app->add(new ConfigCommand());

// Run the application
$app->run();

