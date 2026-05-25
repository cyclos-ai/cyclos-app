<?php

namespace App\Exceptions;

use RuntimeException;

class DemurrageCalculationException extends RuntimeException
{
    public function __construct(string $message = 'Demurrage calculation failed.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
