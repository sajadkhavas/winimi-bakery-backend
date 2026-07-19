<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentProviderException extends RuntimeException
{
    public function __construct(
        string $message = 'ارتباط با درگاه پرداخت ناموفق بود.',
        public readonly ?string $providerCode = null,
    ) {
        parent::__construct($message);
    }
}