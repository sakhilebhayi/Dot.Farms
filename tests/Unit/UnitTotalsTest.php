<?php

namespace Tests\Unit;

use App\Enums\Unit;
use App\Services\Farms\UnitTotals;
use PHPUnit\Framework\TestCase;

class UnitTotalsTest extends TestCase
{
    public function test_kilograms_and_tonnes_are_summed_in_one_weight_total(): void
    {
        $totals = (new UnitTotals)->add('kg', 500)->add('t', 1.5);

        $this->assertSame(2000.0, $totals->amountFor('weight'));
        $this->assertCount(1, $totals->all());
    }

    public function test_incompatible_units_are_never_added_together(): void
    {
        $totals = (new UnitTotals)
            ->add('crate', 10)
            ->add('bag', 4)
            ->add('crate', 5)
            ->add('kg', 100)
            ->add('l', 30);

        $byKey = collect($totals->all())->keyBy('key');

        $this->assertCount(4, $byKey);
        $this->assertSame(15.0, $byKey['crate']['amount']);
        $this->assertSame(4.0, $byKey['bag']['amount']);
        $this->assertSame(100.0, $byKey['weight']['amount']);
        $this->assertSame(30.0, $byKey['volume']['amount']);
    }

    public function test_unknown_legacy_units_stay_in_their_own_bucket(): void
    {
        $totals = (new UnitTotals)->add('sack', 3)->add('kg', 10)->add('sack', 2);

        $byKey = collect($totals->all())->keyBy('key');

        $this->assertSame(5.0, $byKey['sack']['amount']);
        $this->assertSame(10.0, $byKey['weight']['amount']);
    }

    public function test_weight_display_shows_the_tonne_equivalent_from_one_tonne(): void
    {
        $this->assertSame('850 kg', (new UnitTotals)->add('kg', 850)->all()[0]['display']);
        $this->assertSame('12,500 kg (12.5 t)', (new UnitTotals)->add('kg', 12500)->all()[0]['display']);
        $this->assertSame('3 crate', (new UnitTotals)->add('crate', 3)->all()[0]['display']);
    }

    public function test_per_hectare_divides_every_bucket_by_the_area(): void
    {
        $perHa = (new UnitTotals)->add('kg', 9000)->add('crate', 50)->perHectare(4.5);

        $byKey = collect($perHa->all())->keyBy('key');

        $this->assertSame(2000.0, $byKey['weight']['amount']);
        $this->assertEqualsWithDelta(11.11, $byKey['crate']['amount'], 0.01);
    }

    public function test_per_hectare_without_a_usable_area_is_empty(): void
    {
        $this->assertTrue((new UnitTotals)->add('kg', 10)->perHectare(0)->isEmpty());
        $this->assertTrue((new UnitTotals)->add('kg', 10)->perHectare(null)->isEmpty());
    }

    public function test_merge_combines_two_totals_within_their_families(): void
    {
        $a = (new UnitTotals)->add('kg', 100)->add('crate', 1);
        $b = (new UnitTotals)->add('t', 1)->add('bag', 2);

        $merged = collect($a->merge($b)->all())->keyBy('key');

        $this->assertSame(1100.0, $merged['weight']['amount']);
        $this->assertSame(1.0, $merged['crate']['amount']);
        $this->assertSame(2.0, $merged['bag']['amount']);
    }

    public function test_unit_enum_families_and_conversion(): void
    {
        $this->assertSame('weight', Unit::Kg->family());
        $this->assertSame('weight', Unit::Tonne->family());
        $this->assertSame(1000.0, Unit::Tonne->factorToBase());
        $this->assertNotSame(Unit::Bag->family(), Unit::Crate->family());
        $this->assertSame('area', Unit::Hectare->family());
        $this->assertNotContains(Unit::Hectare, Unit::forHarvest());
        $this->assertContains(Unit::Hectare, Unit::forPlanting());
        $this->assertSame(['kg', 't', 'bag', 'crate', 'bunch', 'l', 'head'], array_map(fn (Unit $u) => $u->value, Unit::forHarvest()));
    }
}
