<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an order is attempted while no shift is open: the till is locked until a new shift starts.
 */
class NoOpenShiftException extends RuntimeException
{
    public function __construct(string $message = 'Aucune équipe ouverte : commencez une nouvelle équipe.')
    {
        parent::__construct($message);
    }
}
