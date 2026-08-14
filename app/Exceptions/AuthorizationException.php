<?php

namespace App\Exceptions;

use Exception;

class AuthorizationException extends Exception
{
    public function __construct(string $message = 'Anda tidak memiliki akses untuk melakukan tindakan ini.')
    {
        parent::__construct($message);
    }
}
