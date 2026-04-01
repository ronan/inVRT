<?php

namespace App\Input;

use Symfony\Component\Console\Attribute\Option;

/** Shared options for inVRT commands. */
class InvrtInput
{
    #[Option(description: 'Environment name', shortcut: 'e')]
    public string $environment = 'local';

    #[Option(description: 'Profile name', shortcut: 'p')]
    public string $profile = 'anonymous';

    #[Option(description: 'Device type', shortcut: 'd')]
    public string $device = 'desktop';
}
