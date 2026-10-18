<?php

namespace App\Support\Farms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row of the Removed screen: a soft-deleted record with the words to show
 * for it, when it was removed, and the removed ancestor (if any) that has to be
 * restored before this record can be.
 */
final readonly class RemovedItem
{
    public function __construct(
        public string $type,
        public Model $record,
        public string $title,
        public string $detail,
        public ?Carbon $removedAt,
        public ?RemovedAncestor $blockedBy,
    ) {}

    public function typeLabel(): string
    {
        return ucfirst(RemovedLookup::LABELS[$this->type]);
    }
}
