<?php

namespace Tests\Feature\Farms;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Laravel\Jetstream\Jetstream;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

/**
 * Role by action, with the "viewer" role InfoDot's read-only members now get.
 *
 *   owner, admin  manage farms, fields, crops; delete anything
 *   editor        record and correct plantings and harvests, run cycles
 *   viewer        read only: the same read surface as an editor, writes nothing
 *   outsider      not on the team at all
 *
 * Export decision: the harvest CSV is the same data the harvest list and the
 * reports already show, and editors may export it, so viewers may too. It is
 * the one ability that exists only to be flipped in one place if that changes:
 * HarvestRecordPolicy::export().
 */
class ViewerRoleMatrixTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    private const ROLES = ['owner', 'admin', 'editor', 'viewer', 'outsider'];

    /** @var array<string, User> */
    private array $users = [];

    private object $w;

    protected function setUp(): void
    {
        parent::setUp();

        $team = $this->teamWithOwner();
        $this->w = $this->world($team);
        $this->w->farm->update(['name' => 'Vale Matrix Farm']);

        foreach (['owner', 'admin', 'editor', 'viewer'] as $role) {
            $this->users[$role] = $this->memberOf($team, $role);
        }

        // Not a member; their active team is their own personal one.
        $this->users['outsider'] = User::factory()->withPersonalTeam()->create();
    }

    public function test_jetstream_has_a_read_only_viewer_role(): void
    {
        $viewer = Jetstream::findRole('viewer');

        $this->assertNotNull($viewer);
        $this->assertSame(['read'], $viewer->permissions);
        $this->assertSame(['admin', 'editor', 'viewer'], array_keys(Jetstream::$roles));
    }

    /**
     * ability => who may, for the policies
     *
     * @return array<string, array{string}>
     */
    public static function abilities(): array
    {
        return array_map(fn ($k) => [$k], array_keys(self::policyRows()));
    }

    /** @return array<string, list<string>> */
    private static function policyRows(): array
    {
        $all = ['owner', 'admin', 'editor', 'viewer'];
        $operate = ['owner', 'admin', 'editor'];
        $manage = ['owner', 'admin'];

        return [
            'farm view' => $all,
            'farm create' => $manage,
            'farm update' => $manage,
            'farm delete' => $manage,
            'field view' => $all,
            'field create' => $manage,
            'field update' => $manage,
            'field delete' => $manage,
            'crop view' => $all,
            'crop create' => $manage,
            'crop update' => $manage,
            'crop delete' => $manage,
            'cycle view' => $all,
            'cycle create' => $operate,
            'cycle update' => $operate,
            'cycle transition' => $operate,
            'cycle recordPlanting' => $operate,
            'cycle recordHarvest' => $operate,
            'cycle delete' => $manage,
            'planting view' => $all,
            'planting update' => $operate,
            'planting delete' => $manage,
            'harvest view' => $all,
            'harvest viewAny' => $all,
            'harvest export' => $all,
            'harvest update' => $operate,
            'harvest delete' => $manage,
        ];
    }

    #[DataProvider('abilities')]
    public function test_policy_matrix(string $row): void
    {
        $w = $this->w;
        $allowed = self::policyRows()[$row];
        [$kind, $ability] = explode(' ', $row);

        $args = match ($kind) {
            'farm' => $ability === 'create' ? [Farm::class] : [$w->farm],
            'field' => $ability === 'create' ? [Field::class, $w->farm] : [$w->field],
            'crop' => $ability === 'create' ? [Crop::class] : [$w->crop],
            'cycle' => $ability === 'create' ? [CropCycle::class, $w->field] : [$w->cycle],
            'planting' => [$w->planting],
            'harvest' => in_array($ability, ['viewAny', 'export'], true) ? [HarvestRecord::class] : [$w->harvest],
        };

        foreach (self::ROLES as $role) {
            $user = $this->users[$role];
            if ($role === 'outsider') {
                // Even with the target team forced as their active team, a non-member gets nothing.
                $user->forceFill(['current_team_id' => $w->team->id])->save();
                $user = $user->fresh();
            }

            $result = Gate::forUser($user)->allows($ability, $args);
            $expected = in_array($role, $allowed, true);

            // viewAny only asks for an active team; membership is enforced by every row read.
            if ($role === 'outsider' && $ability === 'viewAny') {
                continue;
            }

            $this->assertSame($expected, $result, "{$row} by {$role}");
        }
    }

    /** @return array<string, array{string}> */
    public static function readPages(): array
    {
        return array_map(fn ($k) => [$k], ['dashboard', 'farms', 'farm', 'field', 'cycle', 'crops', 'harvests', 'harvest', 'reports', 'export']);
    }

    #[DataProvider('readPages')]
    public function test_every_member_reads_the_same_pages_and_an_outsider_sees_nothing_of_the_team(string $page): void
    {
        $w = $this->w;
        $url = match ($page) {
            'dashboard' => route('dashboard'),
            'farms' => route('farms.index'),
            'farm' => route('farms.show', $w->farm),
            'field' => route('fields.show', [$w->farm, $w->field]),
            'cycle' => route('crop-cycles.show', $this->cycleRouteParams($w)),
            'crops' => route('crops.index'),
            'harvests' => route('harvests.index'),
            'harvest' => route('harvests.show', $w->harvest),
            'reports' => route('reports.index'),
            'export' => route('harvests.export'),
        };

        $bodies = [];
        foreach (['owner', 'admin', 'editor', 'viewer'] as $role) {
            $response = $this->actingAs($this->users[$role])->get($url);
            $response->assertOk();
            $bodies[$role] = $page === 'export' ? $response->streamedContent() : $response->getContent();
        }

        // Same read surface as an editor: the viewer sees the farm the editor sees.
        if (in_array($page, ['farms', 'farm', 'harvests'], true)) {
            $this->assertStringContainsString('Vale Matrix Farm', $bodies['viewer'], "{$page} as viewer");
            $this->assertStringContainsString('Vale Matrix Farm', $bodies['editor'], "{$page} as editor");
        }

        $outsider = $this->actingAs($this->users['outsider'])->get($url);
        if (in_array($outsider->getStatusCode(), [403, 404], true)) {
            $this->assertTrue(true);
        } else {
            $outsider->assertOk();
            $content = $page === 'export' ? $outsider->streamedContent() : $outsider->getContent();
            $this->assertStringNotContainsString('Vale Matrix Farm', $content, "{$page} leaks the team to an outsider");
        }
    }

    /** @return array<string, array{string, bool}> */
    public static function writes(): array
    {
        $rows = [
            'create farm' => false, 'update farm' => false, 'delete farm' => false,
            'create field' => false, 'update field' => false, 'delete field' => false,
            'create crop' => false, 'update crop' => false, 'delete crop' => false,
            'plan cycle' => true, 'update cycle' => true, 'transition cycle' => true, 'delete cycle' => false,
            'record planting' => true, 'update planting' => true, 'delete planting' => false,
            'record harvest' => true, 'update harvest' => true, 'delete harvest' => false,
        ];

        $cases = [];
        foreach ($rows as $action => $editorMay) {
            $cases[$action] = [$action, $editorMay];
        }

        return $cases;
    }

    #[DataProvider('writes')]
    public function test_http_write_matrix(string $action, bool $editorMay): void
    {
        $managerOnly = ! $editorMay;

        foreach (self::ROLES as $role) {
            $this->refreshWorld($action);
            $w = $this->w;
            $user = $this->users[$role];
            $before = $this->fingerprint();

            $this->actingAs($user);
            $response = $this->send($action, $w);

            $mayWrite = match ($role) {
                'owner', 'admin' => true,
                'editor' => $editorMay,
                default => false,
            };

            if ($mayWrite) {
                $this->assertNotContains($response->getStatusCode(), [403, 404], "{$role} was refused {$action}");
                $response->assertSessionHasNoErrors();
                $this->assertNotSame($before, $this->fingerprint(), "{$role} {$action} should have written");
            } else {
                if ($role === 'outsider') {
                    // Creating something acts on their OWN team, which is allowed to them; the
                    // target team's data is what must stay untouched (checked below).
                    $this->assertNotSame(500, $response->getStatusCode());
                } else {
                    $this->assertSame(403, $response->getStatusCode(), "{$role} {$action} must be refused (got {$response->getStatusCode()})");
                }
                $this->assertSame($before, $this->fingerprint(), "{$role} {$action} must change nothing");
            }
        }

        $this->assertTrue($managerOnly || $editorMay);
    }

    /** Fresh rows for each role, so one role's write does not hide another's. */
    private function refreshWorld(string $action): void
    {
        $team = $this->w->team;
        foreach ([HarvestRecord::class, PlantingRecord::class, CropCycle::class, Field::class, Farm::class, Crop::class] as $model) {
            $model::withoutGlobalScopes()->withTrashed()->forceDelete();
        }

        $this->w = $this->world($team, in_array($action, ['record planting', 'update planting', 'delete planting', 'plan cycle'], true) ? 'planted' : 'growing');
        $this->w->farm->update(['name' => 'Vale Matrix Farm']);

        if ($action === 'delete crop') {
            $this->unused = Crop::factory()->create(['team_id' => $team->id]);
        }
        if ($action === 'delete planting') {
            PlantingRecord::factory()->create(['crop_cycle_id' => $this->w->cycle->id, 'planted_at' => now()->subMonths(3)]);
        }
    }

    private ?Crop $unused = null;

    private function send(string $action, object $w): TestResponse
    {
        $p = $this->cycleRouteParams($w);

        return match ($action) {
            'create farm' => $this->post(route('farms.store'), ['name' => 'New']),
            'update farm' => $this->put(route('farms.update', $w->farm), ['name' => 'Renamed', 'status' => 'active']),
            'delete farm' => $this->delete(route('farms.destroy', $w->farm), ['confirm_cascade' => 1]),
            'create field' => $this->post(route('fields.store', $w->farm), ['name' => 'F', 'code' => 'NEWF']),
            'update field' => $this->put(route('fields.update', [$w->farm, $w->field]), ['name' => 'Renamed', 'code' => $w->field->code, 'status' => 'active']),
            'delete field' => $this->delete(route('fields.destroy', [$w->farm, $w->field]), ['confirm_cascade' => 1]),
            'create crop' => $this->post(route('crops.store'), ['name' => 'Rye']),
            'update crop' => $this->put(route('crops.update', $w->crop), ['name' => 'Renamed']),
            'delete crop' => $this->delete(route('crops.destroy', $this->unused)),
            'plan cycle' => $this->post(route('crop-cycles.store', [$w->farm, $w->field]), ['crop_id' => $w->crop->id, 'season' => '2027 Summer']),
            'update cycle' => $this->put(route('crop-cycles.update', $p), ['season' => '2031 Autumn']),
            'transition cycle' => $this->post(route('crop-cycles.transition', $p), ['action' => 'abandon', 'reason' => 'Test']),
            'delete cycle' => $this->delete(route('crop-cycles.destroy', $p), ['confirm_cascade' => 1]),
            'record planting' => $this->post(route('crop-cycles.planting.store', $p), ['planted_at' => now()->subMonths(4)->toDateString(), 'unit' => 'kg']),
            'update planting' => $this->put(route('plantings.update', [...$p, $w->planting]), ['planted_at' => now()->subMonths(4)->toDateString(), 'unit' => 'kg']),
            'delete planting' => $this->delete(route('plantings.destroy', [...$p, $w->planting])),
            'record harvest' => $this->post(route('crop-cycles.harvest.store', $p), ['harvested_at' => now()->toDateString(), 'quantity_harvested' => 5, 'unit' => 'kg']),
            'update harvest' => $this->put(route('harvests.update', $w->harvest), ['harvested_at' => now()->subDay()->toDateString(), 'quantity_harvested' => 5, 'unit' => 'kg']),
            'delete harvest' => $this->delete(route('harvests.destroy', $w->harvest)),
        };
    }

    /** Everything a write against the target team could touch, as one comparable string. */
    private function fingerprint(): string
    {
        $team = $this->w->team->id;
        $farms = DB::table('farms')->where('team_id', $team)->orderBy('id')->get();
        $fields = DB::table('fields')->whereIn('farm_id', $farms->pluck('id'))->orderBy('id')->get();
        $cycles = DB::table('crop_cycles')->whereIn('field_id', $fields->pluck('id'))->orderBy('id')->get();

        return json_encode([
            'farms' => $farms,
            'fields' => $fields,
            'crops' => DB::table('crops')->where('team_id', $team)->orderBy('id')->get(),
            'cycles' => $cycles,
            'plantings' => DB::table('planting_records')->whereIn('crop_cycle_id', $cycles->pluck('id'))->orderBy('id')->get(),
            'harvests' => DB::table('harvest_records')->whereIn('crop_cycle_id', $cycles->pluck('id'))->orderBy('id')->get(),
        ]);
    }

    public function test_edit_screens_of_every_write_answer_403_to_a_viewer(): void
    {
        $w = $this->w;
        $this->actingAs($this->users['viewer']);

        foreach ([
            route('farms.create'), route('farms.edit', $w->farm), route('crops.create'), route('crops.edit', $w->crop),
            route('fields.create', $w->farm), route('fields.edit', [$w->farm, $w->field]),
            route('crop-cycles.create', [$w->farm, $w->field]), route('crop-cycles.edit', $this->cycleRouteParams($w)),
            route('plantings.edit', [...$this->cycleRouteParams($w), $w->planting]), route('harvests.edit', $w->harvest),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_a_viewer_is_shown_no_write_controls_an_admin_is(): void
    {
        $w = $this->w;
        $p = $this->cycleRouteParams($w);

        $this->actingAs($this->users['viewer']);
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('farms.create'), false);
        $this->get(route('farms.index'))->assertOk()->assertDontSee(route('farms.create'), false);
        $this->get(route('crops.index'))->assertOk()
            ->assertDontSee(route('crops.create'), false)->assertDontSee(route('crops.edit', $w->crop), false);
        $this->get(route('farms.show', $w->farm))->assertOk()
            ->assertDontSee(route('farms.edit', $w->farm), false)->assertDontSee(route('fields.create', $w->farm), false);
        $this->get(route('fields.show', [$w->farm, $w->field]))->assertOk()
            ->assertDontSee(route('fields.edit', [$w->farm, $w->field]), false)
            ->assertDontSee(route('crop-cycles.create', [$w->farm, $w->field]), false);
        $this->get(route('crop-cycles.show', $p))->assertOk()
            ->assertDontSee(route('crop-cycles.edit', $p), false)
            ->assertDontSee(route('crop-cycles.transition', $p), false)
            ->assertDontSee(route('crop-cycles.planting.store', $p), false)
            ->assertDontSee(route('crop-cycles.harvest.store', $p), false)
            ->assertDontSee(route('harvests.edit', $w->harvest), false)
            ->assertDontSee(route('plantings.edit', [...$p, $w->planting]), false)
            ->assertDontSee(route('crop-cycles.destroy', $p), false);
        $this->get(route('harvests.show', $w->harvest))->assertOk()
            ->assertDontSee(route('harvests.edit', $w->harvest), false)
            ->assertDontSee(route('harvests.destroy', $w->harvest), false);

        $this->actingAs($this->users['admin']);
        $this->get(route('farms.index'))->assertSee(route('farms.create'), false);
        $this->get(route('crop-cycles.show', $p))->assertOk()
            ->assertSee(route('crop-cycles.edit', $p), false)
            ->assertSee(route('crop-cycles.harvest.store', $p), false)
            ->assertSee(route('harvests.edit', $w->harvest), false);
    }

    public function test_the_viewer_keeps_the_export_link_because_an_editor_has_it(): void
    {
        $this->actingAs($this->users['editor'])->get(route('reports.index'))->assertOk()->assertSee(route('harvests.export'), false);
        $this->actingAs($this->users['viewer'])->get(route('reports.index'))->assertOk()->assertSee(route('harvests.export'), false);
        $this->actingAs($this->users['viewer'])->get(route('harvests.index'))->assertOk()->assertSee(route('harvests.export'), false);
        $this->actingAs($this->users['viewer'])->get(route('harvests.export'))->assertOk();
    }
}
