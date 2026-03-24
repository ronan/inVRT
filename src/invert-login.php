#!/usr/bin/env php
<?php

if (!$INVRT_USERNAME || !$INVRT_PASSWORD) {
    echo "⚠️ No username/password found in profile. Skipping login.\n";
    return;
}

if (!$INVRT_URL) {
    fwrite(STDERR, "❌ Profile has credentials but no URL configured. Cannot login.\n");
    exit(1);
}

if (file_exists($INVRT_COOKIES_FILE)) {
    echo "⚠️ Cookies file already exists at $INVRT_COOKIES_FILE. Skipping login to avoid overwriting existing cookies.\n";
    return;
}

if (!isset($_ENV['INVRT_LOGIN_URL'])) {
    $_ENV['INVRT_LOGIN_URL'] = $_ENV['INVRT_URL'] . "/user/login";
}

$script = __DIR__ . "/playwright-login.js";
$return = 0;
$env =  "INVRT_LOGIN_URL=$_ENV[INVRT_LOGIN_URL] " .
        "INVRT_USERNAME=$_ENV[INVRT_USERNAME] " .
        "INVRT_PASSWORD=$_ENV[INVRT_PASSWORD] " .
        "INVRT_COOKIES_FILE=$_ENV[INVRT_COOKIES_FILE] ";

passthru(escapeshellcmd("$env node $script"), $return);

if ($return !== 0) {
    fprintf(STDERR, "❌ Playwright login failed with exit code " . $return . "\n");
    exit($return);
}

echo "✅ Login successful!\n";
