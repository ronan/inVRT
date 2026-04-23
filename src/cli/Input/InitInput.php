<?php

namespace App\Input;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;

class InitInput extends InvrtInput
{
    #[Argument(description: 'Website URL to save in the new config file')]
    public ?string $url = null;

    #[Option(description: 'Skip running baseline after init')]
    public bool $skipBaseline = false;
}
