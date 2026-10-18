<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

/**
 * Company B must never see, search for or restore company A's removed records,
 * even with A's ids smuggled onto the restore route.
 */
class RemovedCrossTeamTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function types(): array
    {
        return array_combine(
            ['farm', 'field', 'crop', 'cycle', 'planting', 'harvest'],
            array_map(fn ($t) => [$t], ['farm', 'field', 'crop', 'cycle', 'planting', 'harvest']),
        );
    }

    private function removedWorld(): object
    {
        $a = $this->world($this->teamWithOwner());
        $a->farm->update(['name' => 'ALPHA-SECRET-FARM']);
        $a->field->update(['name' => 'ALPHA-SECRET-FIELD']);
        $a->crop->update(['name' => 'ALPHA-SECRET-CROP']);
        $a->farm->delete();
        $a->spare = Crop::factory()->create(['team_id' => $a->team->id, 'name' => 'ALPHA-SECRET-SPARE']);
        $a->spare->delete();

        return $a;
    }

    private function idFor(object $a, string $type): int
    {
        return match ($type) {
            'farm' => $a->farm->id,
            'field' => $a->field->id,
            'crop' => $a->spare->id,
            'cycle' => $a->cycle->id,
            'planting' => $a->planting->id,
            'harvest' => $a->harvest->id,
        };
    }

    #[DataProvider('types')]
    public function test_a_smuggled_id_on_the_restore_route_404s_and_changes_nothing(string $type): void
    {
        $a = $this->removedWorld();
        $b = $this->world($this->teamWithOwner());
        $before = $this->fingerprint();

        $this->actingAs($b->team->owner)
            ->post(route('removed.restore', ['type' => $type, 'id' => $this->idFor($a, $type)]))
            ->assertNotFound();

        $this->assertSame($before, $this->fingerprint());
    }

    public function test_company_b_never_sees_company_as_removed_records_in_the_list_or_search(): void
    {
        $this->removedWorld();
        $b = $this->world($this->teamWithOwner());
        $b->planting->delete();
        $this->actingAs($b->team->owner);

        $this->get(route('removed.index'))->assertOk()->assertDontSee('ALPHA-SECRET');
        $this->get(route('removed.index', ['q' => 'ALPHA-SECRET']))->assertOk()
            ->assertDontSee('ALPHA-SECRET-FARM')->assertDontSee('ALPHA-SECRET-FIELD')->assertDontSee('ALPHA-SECRET-CROP')->assertDontSee('ALPHA-SECRET-SPARE');
        $this->assertSame(0, $this->get(route('removed.index', ['q' => 'ALPHA-SECRET']))->viewData('items')->total());
        $this->assertSame(1, $this->get(route('removed.index'))->viewData('items')->total());

        foreach (['farm', 'field', 'crop', 'cycle', 'planting', 'harvest'] as $type) {
            $this->assertSame(0, $this->get(route('removed.index', ['type' => $type, 'q' => 'ALPHA']))->viewData('items')->total());
        }
    }

    public function test_a_removed_chain_under_a_removed_farm_is_listed_for_its_own_team_only(): void
    {
        $a = $this->removedWorld();
        $b = $this->world($this->teamWithOwner());
        $b->farm->delete();

        $aKeys = $this->actingAs($a->team->owner)->get(route('removed.index'))->viewData('items')->getCollection()->map(fn ($i) => $i->type.':'.$i->record->id)->all();
        $bKeys = $this->actingAs($b->team->owner)->get(route('removed.index'))->viewData('items')->getCollection()->map(fn ($i) => $i->type.':'.$i->record->id)->all();

        $this->assertContains('harvest:'.$a->harvest->id, $aKeys);
        $this->assertNotContains('harvest:'.$b->harvest->id, $aKeys);
        $this->assertContains('harvest:'.$b->harvest->id, $bKeys);
        $this->assertSame([], array_intersect($aKeys, $bKeys));
        $this->assertCount(6, $aKeys);
        $this->assertCount(5, $bKeys);
    }

    public function test_an_admin_of_one_team_is_an_outsider_to_the_others_removed_records(): void
    {
        $a = $this->removedWorld();
        $b = $this->world($this->teamWithOwner());
        $admin = $this->memberOf($b->team, 'admin');
        $before = $this->fingerprint();

        $this->actingAs($admin)->post(route('removed.restore', ['type' => 'farm', 'id' => $a->farm->id]))->assertNotFound();

        $this->assertSame($before, $this->fingerprint());
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function fingerprint(): array
    {
        $out = [];
        foreach (['farms', 'fields', 'crops', 'crop_cycles', 'planting_records', 'harvest_records', 'record_restorations'] as $table) {
            $out[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }

        return $out;
    }
}
