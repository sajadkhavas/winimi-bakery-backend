<?php

namespace App\Exceptions;

use RuntimeException;

final class GoogleAuthException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $mode = 'login',
        string $message = 'ورود با گوگل تکمیل نشد.',
    ) {
        parent::__construct($message);
    }
}
