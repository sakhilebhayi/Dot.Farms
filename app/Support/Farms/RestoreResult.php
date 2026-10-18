<?php

namespace App\Support\Farms;

use Illuminate\Support\Str;

/** What a restore brought back, in words for the banner and in numbers for the audit row. */
final readonly class RestoreResult
{
    public function __construct(
        public string $type,
        public string $label,
        public int $fields = 0,
        public int $cycles = 0,
        public int $records = 0,
        public int $cyclesLeftRemoved = 0,
    ) {}

    /** How many descendants came back with the record itself. */
    public function restoredWith(): int
    {
        return $this->fields + $this->cycles + $this->records;
    }

    public function message(): string
    {
        $parts = array_values(array_filter([
            $this->fields > 0 ? $this->fields.' '.Str::plural('field', $this->fields) : null,
            $this->cycles > 0 ? $this->cycles.' '.Str::plural('crop cycle', $this->cycles) : null,
            $this->records > 0 ? $this->records.' '.Str::plural('record', $this->records) : null,
        ]));

        $with = match (count($parts)) {
            0 => '',
            1 => ' with '.$parts[0],
            default => ' with '.implode(', ', array_slice($parts, 0, -1)).' and '.end($parts),
        };

        $message = 'Restored '.RemovedLookup::LABELS[$this->type].' "'.$this->label.'"'.$with.'.';

        if ($this->cyclesLeftRemoved > 0) {
            $message .= ' '.$this->cyclesLeftRemoved.' '.Str::plural('crop cycle', $this->cyclesLeftRemoved).' left removed because their crop is removed; restore the crop first.';
        }

        return $message;
    }
}
