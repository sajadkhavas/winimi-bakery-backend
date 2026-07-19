<?php

namespace App\Exceptions;

use RuntimeException;

class CheckoutUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('ثبت سفارش در حال حاضر فعال نیست.');
    }
}
