<?php

namespace App\Input;

use Symfony\Component\Console\Attribute\Argument;

class InitInput extends InvrtInput
{
    #[Argument(description: 'Website URL to save in the new config file')]
    public ?string $url = null;
}
