<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Thrown by CropCycleLifecycle when an action would break the cycle's state
 * machine or its record rules. It is a ValidationException so an HTTP caller
 * gets the normal redirect-back-with-errors (or 422 JSON) behaviour for free.
 */
class InvalidCycleTransition extends ValidationException
{
    public static function because(string $message, string $field = 'cycle'): self
    {
        return self::withMessages([$field => $message]);
    }
}
