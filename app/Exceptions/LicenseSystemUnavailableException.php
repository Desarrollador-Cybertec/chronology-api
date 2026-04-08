<?php

namespace App\Exceptions;

use RuntimeException;

class LicenseSystemUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'El sistema de licencias no está disponible en este momento. La operación fue bloqueada por seguridad.')
    {
        parent::__construct($message, 503);
    }
}
