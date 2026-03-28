<?php

namespace App\Support;

trait PathHelper
{
    protected function joinPath(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
