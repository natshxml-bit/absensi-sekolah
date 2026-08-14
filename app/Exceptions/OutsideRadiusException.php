<?php

namespace App\Exceptions;

use Exception;

class OutsideRadiusException extends Exception
{
    public function __construct(string $message = 'Anda berada di luar radius absensi sekolah.')
    {
        parent::__construct($message);
    }
}
