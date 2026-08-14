<?php

namespace App\Exceptions;

use Exception;

class AlreadyCheckedInException extends Exception
{
    public function __construct(string $message = 'Siswa sudah melakukan absensi hari ini.')
    {
        parent::__construct($message);
    }
}
