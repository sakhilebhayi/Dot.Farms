<?php

namespace Tests\Unit;

use App\Enums\CropCycleStatus as S;
use PHPUnit\Framework\TestCase;

class CropCycleStatusTest extends TestCase
{
    public function test_forward_transitions_are_allowed(): void
    {
        $this->assertTrue(S::Planned->canTransitionTo(S::Planted));
        $this->assertTrue(S::Planted->canTransitionTo(S::Growing));
        $this->assertTrue(S::Planted->canTransitionTo(S::Harvested));
        $this->assertTrue(S::Growing->canTransitionTo(S::Harvested));
        $this->assertTrue(S::Growing->canTransitionTo(S::Failed));
        $this->assertTrue(S::Planted->canTransitionTo(S::Abandoned));
        $this->assertTrue(S::Planned->canTransitionTo(S::Abandoned));
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $this->assertFalse(S::Planned->canTransitionTo(S::Harvested));
        $this->assertFalse(S::Planned->canTransitionTo(S::Growing));
        $this->assertFalse(S::Planned->canTransitionTo(S::Failed));
        $this->assertFalse(S::Growing->canTransitionTo(S::Planted));
        $this->assertFalse(S::Harvested->canTransitionTo(S::Failed));
        $this->assertFalse(S::Failed->canTransitionTo(S::Harvested));
        $this->assertFalse(S::Abandoned->canTransitionTo(S::Harvested));
        $this->assertFalse(S::Planted->canTransitionTo(S::Planted));
    }

    public function test_a_closed_cycle_can_only_go_back_to_a_resting_open_state(): void
    {
        foreach ([S::Harvested, S::Failed, S::Abandoned] as $closed) {
            $this->assertTrue($closed->isClosed());
            $this->assertTrue($closed->canTransitionTo(S::Planned));
            $this->assertTrue($closed->canTransitionTo(S::Planted));
            $this->assertTrue($closed->canTransitionTo(S::Growing));
        }
    }

    public function test_only_planted_and_growing_accept_harvests(): void
    {
        $this->assertTrue(S::Planted->acceptsHarvests());
        $this->assertTrue(S::Growing->acceptsHarvests());
        foreach ([S::Planned, S::Harvested, S::Failed, S::Abandoned] as $s) {
            $this->assertFalse($s->acceptsHarvests(), $s->value);
        }
    }

    public function test_open_means_not_yet_closed(): void
    {
        $this->assertSame(
            ['planned', 'planted', 'growing'],
            array_map(fn (S $s) => $s->value, S::open())
        );
    }
}
