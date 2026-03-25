<?php

namespace App\Service;

class CookieService
{
    private const NETSCAPE_HEADER = "# Netscape HTTP Cookie File\n"
        . "# http://curl.haxx.se/rfc/cookie_spec.html\n"
        . "# This is a generated file!  Do not edit.\n\n";

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

            $cookies = self::loadCookies($jsonFilePath);
            $txtFilePath = self::getTxtFilePath($jsonFilePath);
            $content = self::formatNetscapeContent($cookies);

            file_put_contents($txtFilePath, $content);
            echo "📄 Cookies converted to wget format: " . $txtFilePath . "\n";
        } catch (\Exception $error) {
            fwrite(STDERR, "⚠️  Warning: Could not convert cookies to wget format: " . $error->getMessage() . "\n");
        }
    }

    /**
     * Load cookies from JSON file
     */
    private static function loadCookies(string $jsonFilePath): array
    {
        $content = file_get_contents($jsonFilePath);
        $cookies = json_decode($content, true);

        return is_array($cookies) ? $cookies : [];
    }

    /**
     * Get the txt file path from json path
     */
    private static function getTxtFilePath(string $jsonFilePath): string
    {
        return str_replace('.json', '.txt', $jsonFilePath);
    }

    /**
     * Format cookies in Netscape format
     */
    private static function formatNetscapeContent(array $cookies): string
    {
        $content = self::NETSCAPE_HEADER;

        foreach ($cookies as $cookie) {
            $content .= self::formatCookie($cookie);
        }

        return $content;
    }

    /**
     * Format a single cookie in Netscape format
     */
    private static function formatCookie(array $cookie): string
    {
        $domain = $cookie['domain'] ?? '.localhost';
        $secure = ($cookie['secure'] ?? false) ? 'TRUE' : 'FALSE';
        $path = $cookie['path'] ?? '/';
        $expires = $cookie['expires'] ?? '0';
        $name = $cookie['name'] ?? '';
        $value = $cookie['value'] ?? '';

        return "{$domain}\t{$secure}\t{$path}\t{$secure}\t{$expires}\t{$name}\t{$value}\n";
    }
}
