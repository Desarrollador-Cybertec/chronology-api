<?php

namespace App\Exceptions;

use RuntimeException;

class LicenseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        int $code = 403,
    ) {
        parent::__construct($message, $code);
    }
}
