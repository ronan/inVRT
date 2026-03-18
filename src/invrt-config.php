#!/usr/bin/env php
<?php

require_once __DIR__ . '/invrt-utils.inc.php';

$config = loadConfig(joinPath($_ENV['INVRT_DIRECTORY'], 'config.yaml'));

foreach ($config as $key => $value) {
    echo("$key:  $value\n");
}