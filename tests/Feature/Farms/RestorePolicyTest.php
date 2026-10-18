<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

/**
 * Restoring is a management act, like deleting: owner and admin only. An editor
 * (who can correct records but not delete them) and a viewer cannot bring a
 * deleted record back, and someone from another company cannot either.
 */
class RestorePolicyTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: bool}> */
    public static function matrix(): array
    {
        $cases = [];
        foreach (['farm', 'field', 'crop', 'cycle', 'planting', 'harvest'] as $type) {
            foreach (['owner' => true, 'admin' => true, 'editor' => false, 'viewer' => false, 'outsider' => false] as $role => $allowed) {
                $cases["$role ".($allowed ? 'can' : 'cannot')." restore a $type"] = [$type, $role, $allowed];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_restore_is_owner_and_admin_only(string $type, string $role, bool $allowed): void
    {
        $w = $this->world($this->teamWithOwner());
        $other = $this->world($this->teamWithOwner());
        $crop = Crop::factory()->create(['team_id' => $w->team->id]);

        $record = match ($type) {
            'farm' => $w->farm,
            'field' => $w->field,
            'crop' => $crop,
            'cycle' => $w->cycle,
            'planting' => $w->planting,
            'harvest' => $w->harvest,
        };
        $record->delete();

        $user = $role === 'outsider' ? $other->team->owner : $this->memberOf($w->team, $role);

        $this->assertSame($allowed, Gate::forUser($user)->allows('restore', $record));
    }
}
