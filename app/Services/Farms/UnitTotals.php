<?php

namespace App\Services\Farms;

use App\Enums\Unit;

/**
 * Accumulates quantities by unit family so mixed units are never added.
 *
 * kg and t land in one `weight` bucket held in kg; every other unit keeps its
 * own bucket (a crate is never added to a bag). A unit the enum does not know
 * (legacy free text) gets a bucket keyed by its raw value, so it is shown on
 * its own rather than guessed at or dropped.
 */
final class UnitTotals
{
    /** @var array<string, array{key: string, unit: string, amount: float}> */
    private array $buckets = [];

    public function add(?string $unit, float|int|string|null $quantity): self
    {
        $unit = trim((string) $unit);
        $quantity = (float) $quantity;

        $enum = Unit::tryFrom($unit);
        if ($enum) {
            $key = $enum->family();
            $base = Unit::baseOf($key)->value;
            $amount = $quantity * $enum->factorToBase();
        } else {
            $key = $unit === '' ? 'unspecified' : $unit;
            $base = $unit === '' ? 'unspecified' : $unit;
            $amount = $quantity;
        }

        $this->buckets[$key] ??= ['key' => $key, 'unit' => $base, 'amount' => 0.0];
        $this->buckets[$key]['amount'] += $amount;

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->buckets === [];
    }

    public function amountFor(string $familyKey): float
    {
        return $this->buckets[$familyKey]['amount'] ?? 0.0;
    }

    public function merge(self $other): self
    {
        $merged = clone $this;
        foreach ($other->buckets as $bucket) {
            $merged->buckets[$bucket['key']] ??= ['key' => $bucket['key'], 'unit' => $bucket['unit'], 'amount' => 0.0];
            $merged->buckets[$bucket['key']]['amount'] += $bucket['amount'];
        }

        return $merged;
    }

    /** Every bucket divided by an area in hectares. Empty when the area is missing or not positive. */
    public function perHectare(float|int|string|null $hectares): self
    {
        $out = new self;
        $hectares = (float) $hectares;
        if ($hectares <= 0) {
            return $out;
        }
        foreach ($this->buckets as $bucket) {
            $out->buckets[$bucket['key']] = [...$bucket, 'amount' => $bucket['amount'] / $hectares];
        }

        return $out;
    }

    /** @return list<array{key: string, unit: string, amount: float, display: string}> */
    public function all(): array
    {
        $rows = array_map(fn (array $b) => [...$b, 'display' => self::format($b)], array_values($this->buckets));

        // Weight first (the headline figure), then the rest alphabetically for a stable order.
        usort($rows, fn ($a, $b) => [$a['key'] !== 'weight', $a['key']] <=> [$b['key'] !== 'weight', $b['key']]);

        return $rows;
    }

    /** "12,500 kg (12.5 t) · 40 crate", or the given fallback when there is nothing to show. */
    public function toString(string $empty = '—'): string
    {
        if ($this->isEmpty()) {
            return $empty;
        }

        return implode(' · ', array_column($this->all(), 'display'));
    }

    /** @param array{key: string, unit: string, amount: float} $bucket */
    private static function format(array $bucket): string
    {
        $text = self::number($bucket['amount']).' '.$bucket['unit'];

        if ($bucket['key'] === 'weight' && $bucket['amount'] >= 1000) {
            $text .= ' ('.self::number($bucket['amount'] / 1000).' t)';
        }

        return $text;
    }

    private static function number(float $value): string
    {
        $text = number_format($value, 2, '.', ',');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
