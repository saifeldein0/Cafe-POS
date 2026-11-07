<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when starting a shift while another one is still open: only one shift at a time.
 */
class ShiftAlreadyOpenException extends RuntimeException
{
    public function __construct(string $message = 'Une équipe est déjà en cours.')
    {
        parent::__construct($message);
    }
}
