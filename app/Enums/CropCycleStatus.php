<?php

namespace App\Enums;

/**
 * The crop-cycle state machine, in one place.
 *
 *   planned ──► planted ──► growing ──► harvested   (closed by an explicit action)
 *      │           │           │
 *      └───────────┴───────────┴──────► failed / abandoned   (both need a reason)
 *
 * Partial harvests are allowed while a cycle is planted or growing; the cycle
 * only becomes `harvested` when someone closes it. A closed cycle (harvested,
 * failed, abandoned) can be re-opened, but only back to a resting open state
 * that the recorded facts support (see CropCycleLifecycle::restingStatus).
 *
 * There is deliberately no automatic planted -> growing step. "Growing" is a
 * statement about the crop (it has emerged), and elapsed days since sowing do
 * not establish that, so it is an explicit "Mark growing" action instead of a
 * scheduled job that would guess.
 */
enum CropCycleStatus: string
{
    case Planned = 'planned';
    case Planted = 'planted';
    case Growing = 'growing';
    case Harvested = 'harvested';
    case Failed = 'failed';
    case Abandoned = 'abandoned';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planned => [self::Planted, self::Abandoned],
            self::Planted => [self::Growing, self::Harvested, self::Failed, self::Abandoned],
            self::Growing => [self::Harvested, self::Failed, self::Abandoned],
            // Re-opening: back to whichever open state the facts support.
            self::Harvested, self::Failed, self::Abandoned => [self::Planned, self::Planted, self::Growing],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Harvested, self::Failed, self::Abandoned], true);
    }

    public function isOpen(): bool
    {
        return ! $this->isClosed();
    }

    /** Planted or growing: the crop is in the ground and may be harvested from. */
    public function isInSeason(): bool
    {
        return $this === self::Planted || $this === self::Growing;
    }

    public function acceptsHarvests(): bool
    {
        return $this->isInSeason();
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Planned, self::Planted, self::Growing];
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::open());
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Hex colour used for the status badge (same palette as the rest of the app). */
    public function color(): string
    {
        return match ($this) {
            self::Planted, self::Growing => '#f59e0b',
            self::Harvested => '#22c55e',
            self::Failed => '#ef4444',
            self::Planned, self::Abandoned => '#a1a1aa',
        };
    }
}
