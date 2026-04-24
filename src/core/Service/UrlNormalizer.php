<?php

namespace InVRT\Core\Service;

class UrlNormalizer
{
    /** Normalize a URL, ensuring scheme and lowercase host. Returns '' on parse failure. */
    public static function normalize(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return '';
        }

        $scheme   = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'http';
        $host     = isset($parts['host']) ? strtolower($parts['host']) : '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path     = $parts['path'] ?? '';
        $query    = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . '://' . $host . $port . $path . $query . $fragment;
    }
}
