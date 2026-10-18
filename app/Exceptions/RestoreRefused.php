<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * A restore the rules do not allow (a parent is still removed, a code is taken,
 * the cycle cannot accept the record). It is a ValidationException under the
 * `restore` key, so the Removed screen shows it as an ordinary error banner.
 */
class RestoreRefused extends ValidationException
{
    public static function because(string $message): self
    {
        return self::withMessages(['restore' => $message]);
    }

    public static function parentRemoved(string $childType, string $childName, string $parentType, string $parentName): self
    {
        return self::because("The {$childType} \"{$childName}\" cannot be restored while its {$parentType} \"{$parentName}\" is removed. Restore the {$parentType} first.");
    }
}
