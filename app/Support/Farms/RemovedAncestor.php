<?php

namespace App\Support\Farms;

use Illuminate\Database\Eloquent\Model;

/** A removed parent that blocks restoring one of its children. */
final readonly class RemovedAncestor
{
    public function __construct(public string $type, public Model $record, public string $name) {}

    public function label(): string
    {
        return RemovedLookup::LABELS[$this->type];
    }
}
