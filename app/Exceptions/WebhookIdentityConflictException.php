<?php

namespace App\Exceptions;

use Exception;

class WebhookIdentityConflictException extends Exception
{
    public function __construct()
    {
        parent::__construct(
            'The provider event identity already exists with a different payload.'
        );
    }
}
