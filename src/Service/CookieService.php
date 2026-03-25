<?php

namespace App\Service;

class CookieService
{
    /**
     * Convert cookies.json to wget/curl compatible Netscape format
     */
    public static function convertToNetscapeFormat(string $jsonFilePath): void
    {
        try {
            if (!file_exists($jsonFilePath)) {
                echo "ℹ️  Cookies file not found. Skipping wget format conversion.\n";
                return;
            }

            $cookiesJson = json_decode(file_get_contents($jsonFilePath), true);
            $txtFilePath = str_replace('.json', '.txt', $jsonFilePath);

            // Netscape format header
            $netscapeFormat = "# Netscape HTTP Cookie File\n";
            $netscapeFormat .= "# http://curl.haxx.se/rfc/cookie_spec.html\n";
            $netscapeFormat .= "# This is a generated file!  Do not edit.\n\n";

            // Convert each cookie
            if (is_array($cookiesJson)) {
                foreach ($cookiesJson as $cookie) {
                    $domain = isset($cookie['domain']) ? $cookie['domain'] : '.localhost';
                    $flag = (isset($cookie['secure']) && $cookie['secure']) ? 'TRUE' : 'FALSE';
                    $path = isset($cookie['path']) ? $cookie['path'] : '/';
                    $secure = (isset($cookie['secure']) && $cookie['secure']) ? 'TRUE' : 'FALSE';
                    $expiration = isset($cookie['expires']) ? $cookie['expires'] : '0';
                    $name = isset($cookie['name']) ? $cookie['name'] : '';
                    $value = isset($cookie['value']) ? $cookie['value'] : '';

                    $netscapeFormat .= "{$domain}\t{$flag}\t{$path}\t{$secure}\t{$expiration}\t{$name}\t{$value}\n";
                }
            }

            file_put_contents($txtFilePath, $netscapeFormat);
            echo "📄 Cookies converted to wget format: " . $txtFilePath . "\n";
        } catch (\Exception $error) {
            fwrite(STDERR, "⚠️  Warning: Could not convert cookies to wget format: " . $error->getMessage() . "\n");
        }
    }
}
