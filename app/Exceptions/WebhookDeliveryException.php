<?php

namespace App\Exceptions;

use RuntimeException;

class WebhookDeliveryException extends RuntimeException
{
    public function __construct(string $message = 'Webhook delivery failed.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
