<?php

namespace App\Enums;

/**
 * Units a quantity can be recorded in.
 *
 * Units belong to families. Only units in the same family are convertible,
 * so only those may ever be added together: kg and t share the `weight`
 * family (1 t = 1000 kg); every other unit is its own family, because a crate
 * is not a bag and neither is a kilogram. Anything not in this enum (legacy
 * free-text values) is treated by UnitTotals as a family of its own.
 *
 * Harvests are recorded in the harvest units; plantings in the planting
 * units (which add the area planted and seed/seedling counts).
 */
enum Unit: string
{
    case Kg = 'kg';
    case Tonne = 't';
    case Bag = 'bag';
    case Crate = 'crate';
    case Bunch = 'bunch';
    case Litre = 'l';
    case Head = 'head';
    case Hectare = 'ha';
    case Seed = 'seed';
    case Seedling = 'seedling';

    public function label(): string
    {
        return match ($this) {
            self::Kg => 'kg',
            self::Tonne => 't',
            self::Litre => 'l',
            self::Hectare => 'ha',
            default => $this->value,
        };
    }

    public function fullName(): string
    {
        return match ($this) {
            self::Kg => 'Kilograms (kg)',
            self::Tonne => 'Tonnes (t)',
            self::Bag => 'Bags',
            self::Crate => 'Crates',
            self::Bunch => 'Bunches',
            self::Litre => 'Litres (l)',
            self::Head => 'Heads',
            self::Hectare => 'Hectares (ha)',
            self::Seed => 'Seeds',
            self::Seedling => 'Seedlings',
        };
    }

    /** Key shared by every unit this one can be converted to. */
    public function family(): string
    {
        return match ($this) {
            self::Kg, self::Tonne => 'weight',
            self::Litre => 'volume',
            self::Hectare => 'area',
            default => $this->value,
        };
    }

    /** Multiply a quantity in this unit by this to get the family's base unit (kg for weight). */
    public function factorToBase(): float
    {
        return match ($this) {
            self::Tonne => 1000.0,
            default => 1.0,
        };
    }

    /** The unit a family's totals are expressed in. */
    public static function baseOf(string $family): ?self
    {
        return match ($family) {
            'weight' => self::Kg,
            'volume' => self::Litre,
            'area' => self::Hectare,
            default => self::tryFrom($family),
        };
    }

    /** @return list<self> */
    public static function forHarvest(): array
    {
        return [self::Kg, self::Tonne, self::Bag, self::Crate, self::Bunch, self::Litre, self::Head];
    }

    /** @return list<self> */
    public static function forPlanting(): array
    {
        return [self::Kg, self::Tonne, self::Bag, self::Litre, self::Head, self::Seed, self::Seedling, self::Hectare];
    }

    /** @return list<string> */
    public static function harvestValues(): array
    {
        return array_map(fn (self $u) => $u->value, self::forHarvest());
    }

    /** @return list<string> */
    public static function plantingValues(): array
    {
        return array_map(fn (self $u) => $u->value, self::forPlanting());
    }
}
