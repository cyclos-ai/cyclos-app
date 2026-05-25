<?php

namespace App\Exceptions;

use RuntimeException;

class TenantNotFoundException extends RuntimeException
{
    public function __construct(string $identifier = '', int $code = 0, ?\Throwable $previous = null)
    {
        $message = $identifier
            ? "Tenant not found: {$identifier}"
            : 'Tenant not found.';

        parent::__construct($message, $code, $previous);
    }
}
