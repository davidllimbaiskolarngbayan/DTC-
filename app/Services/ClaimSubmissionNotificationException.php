<?php

namespace App\Services;

use RuntimeException;

class ClaimSubmissionNotificationException extends RuntimeException
{
    public function __construct(
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
