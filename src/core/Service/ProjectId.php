<?php

namespace InVRT\Core\Service;

/** Stable short project/page identifier encoding. Mirrored in src/js/*.js. */
class ProjectId
{
    private const ALPHABET = 'swxdyktzhgjfblrpmcqvn';

    /** Generate a new project ID from a URL + random seed. */
    public static function generate(string $url): string
    {
        return self::encode($url, random_int(0, 0xFFFF));
    }

    /** Deterministic short ID from a value and optional 16-bit seed. */
    public static function encode(string $value, int $seed = 0): string
    {
        $hash   = (int) hexdec(hash('crc32b', $value));
        $number = (($hash & 0xFFFFFFFF) << 16) | ($seed & 0xFFFF);
        $base   = strlen(self::ALPHABET);

        if ($number === 0) {
            return self::ALPHABET[0];
        }

        $encoded = '';
        while ($number > 0) {
            $encoded = self::ALPHABET[$number % $base] . $encoded;
            $number  = intdiv($number, $base);
        }
        return $encoded;
    }
}
