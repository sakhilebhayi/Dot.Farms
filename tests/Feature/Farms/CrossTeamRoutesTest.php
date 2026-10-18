<?php

namespace Tests\Feature\Farms;

use App\Models\CropCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

/**
 * Team B's owner tries every route that takes an id against team A's
 * records, with a payload that would succeed if the route were open. Each must
 * 404 (the record is invisible to B, not merely forbidden) and leave A's data
 * untouched.
 */
class CrossTeamRoutesTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: \Closure, 3?: \Closure}> */
    public static function foreignRoutes(): array
    {
        $cycle = fn ($w) => [$w->farm, $w->field, $w->cycle];
        $good = ['planted_at' => '2026-01-01', 'unit' => 'kg'];
        $harvest = ['harvested_at' => '2026-06-01', 'quantity_harvested' => 1, 'unit' => 'kg'];

        return [
            'view farm' => ['get', 'farms.show', fn ($w) => [$w->farm]],
            'edit farm' => ['get', 'farms.edit', fn ($w) => [$w->farm]],
            'update farm' => ['put', 'farms.update', fn ($w) => [$w->farm], fn () => ['name' => 'Hijacked', 'status' => 'inactive']],
            'delete farm' => ['delete', 'farms.destroy', fn ($w) => [$w->farm], fn () => ['confirm_cascade' => 1]],
            'new field form' => ['get', 'fields.create', fn ($w) => [$w->farm]],
            'create field' => ['post', 'fields.store', fn ($w) => [$w->farm], fn () => ['name' => 'X', 'code' => 'XX']],
            'view field' => ['get', 'fields.show', fn ($w) => [$w->farm, $w->field]],
            'edit field' => ['get', 'fields.edit', fn ($w) => [$w->farm, $w->field]],
            'update field' => ['put', 'fields.update', fn ($w) => [$w->farm, $w->field], fn ($w) => ['name' => 'Hijacked', 'code' => $w->field->code, 'status' => 'retired']],
            'delete field' => ['delete', 'fields.destroy', fn ($w) => [$w->farm, $w->field], fn () => ['confirm_cascade' => 1]],
            'new cycle form' => ['get', 'crop-cycles.create', fn ($w) => [$w->farm, $w->field]],
            'create cycle' => ['post', 'crop-cycles.store', fn ($w) => [$w->farm, $w->field], fn ($w) => ['crop_id' => $w->crop->id, 'season' => 'X']],
            'view cycle' => ['get', 'crop-cycles.show', $cycle],
            'edit cycle' => ['get', 'crop-cycles.edit', $cycle],
            'update cycle' => ['put', 'crop-cycles.update', $cycle, fn () => ['season' => 'Hijacked']],
            'delete cycle' => ['delete', 'crop-cycles.destroy', $cycle, fn () => ['confirm_cascade' => 1]],
            'transition cycle' => ['post', 'crop-cycles.transition', $cycle, fn () => ['action' => 'abandon', 'reason' => 'x']],
            'record planting' => ['post', 'crop-cycles.planting.store', $cycle, fn () => $good],
            'edit planting' => ['get', 'plantings.edit', fn ($w) => [$w->farm, $w->field, $w->cycle, $w->planting]],
            'update planting' => ['put', 'plantings.update', fn ($w) => [$w->farm, $w->field, $w->cycle, $w->planting], fn () => [...$good, 'quantity_planted' => 1]],
            'delete planting' => ['delete', 'plantings.destroy', fn ($w) => [$w->farm, $w->field, $w->cycle, $w->planting]],
            'record harvest' => ['post', 'crop-cycles.harvest.store', $cycle, fn () => $harvest],
            'view harvest' => ['get', 'harvests.show', fn ($w) => [$w->harvest]],
            'edit harvest' => ['get', 'harvests.edit', fn ($w) => [$w->harvest]],
            'update harvest' => ['put', 'harvests.update', fn ($w) => [$w->harvest], fn () => [...$harvest, 'quantity_harvested' => 1]],
            'delete harvest' => ['delete', 'harvests.destroy', fn ($w) => [$w->harvest]],
            'edit crop' => ['get', 'crops.edit', fn ($w) => [$w->crop]],
            'update crop' => ['put', 'crops.update', fn ($w) => [$w->crop], fn () => ['name' => 'Hijacked']],
            'delete crop' => ['delete', 'crops.destroy', fn ($w) => [$w->crop]],
        ];
    }

    #[DataProvider('foreignRoutes')]
    public function test_team_b_cannot_reach_team_as_records(string $method, string $name, \Closure $params, ?\Closure $payload = null): void
    {
        $a = $this->world($this->teamWithOwner());
        $b = $this->world($this->teamWithOwner());
        $before = $this->fingerprint();

        $this->actingAs($b->team->owner);
        $response = $this->{$method}(route($name, $params($a)), $payload ? $payload($a) : []);

        $response->assertNotFound();
        $this->assertSame($before, $this->fingerprint(), "$name changed team A's data");
    }

    public function test_a_foreign_id_smuggled_into_my_own_url_chain_404s(): void
    {
        $a = $this->world($this->teamWithOwner());
        $b = $this->world($this->teamWithOwner());
        $this->actingAs($b->team->owner);

        $this->get(route('fields.show', [$b->farm, $a->field]))->assertNotFound();
        $this->get(route('crop-cycles.show', [$b->farm, $b->field, $a->cycle]))->assertNotFound();
        $this->get(route('plantings.edit', [$b->farm, $b->field, $b->cycle, $a->planting]))->assertNotFound();
        $this->post(route('crop-cycles.harvest.store', [$b->farm, $b->field, $a->cycle]), ['harvested_at' => '2026-06-01', 'quantity_harvested' => 1, 'unit' => 'kg'])->assertNotFound();
        $this->post(route('crop-cycles.transition', [$b->farm, $a->field, $b->cycle]), ['action' => 'abandon', 'reason' => 'x'])->assertNotFound();
    }

    public function test_a_cycle_cannot_be_planned_with_another_teams_crop(): void
    {
        $a = $this->world($this->teamWithOwner());
        $b = $this->world($this->teamWithOwner());
        $this->actingAs($b->team->owner);

        $this->post(route('crop-cycles.store', [$b->farm, $b->field]), ['crop_id' => $a->crop->id, 'season' => 'X'])
            ->assertSessionHasErrors('crop_id');
        $this->put(route('crop-cycles.update', [$b->farm, $b->field, $b->cycle]), ['season' => 'X', 'crop_id' => $a->crop->id])
            ->assertSessionHasErrors('crop_id');

        $this->assertSame(1, CropCycle::withoutGlobalScopes()->where('crop_id', $a->crop->id)->count());
    }

    public function test_listings_never_show_another_teams_rows(): void
    {
        $a = $this->world($this->teamWithOwner());
        $a->farm->update(['name' => 'ALPHA-SECRET-FARM']);
        $a->crop->update(['name' => 'ALPHA-SECRET-CROP']);
        $b = $this->world($this->teamWithOwner());
        $this->actingAs($b->team->owner);

        foreach (['dashboard', 'farms.index', 'crops.index', 'harvests.index', 'reports.index'] as $name) {
            $this->get(route($name))->assertOk()->assertDontSee('ALPHA-SECRET');
        }
        $this->get(route('harvests.export'))->assertOk()->assertDontSee('ALPHA-SECRET');
    }

    private function fingerprint(): array
    {
        $out = [];
        foreach (['farms', 'fields', 'crops', 'crop_cycles', 'planting_records', 'harvest_records'] as $table) {
            $out[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }

        return $out;
    }
}
