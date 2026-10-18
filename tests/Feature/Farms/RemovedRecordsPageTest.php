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
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsFarmWorld;
use Tests\TestCase;

/**
 * The Removed page: a company's soft-deleted records of every type, newest
 * removal first, with a type filter, a search box and pagination.
 */
class RemovedRecordsPageTest extends TestCase
{
    use BuildsFarmWorld;
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('removed.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_is_empty_when_nothing_has_been_removed(): void
    {
        $w = $this->world($this->teamWithOwner());

        $this->actingAs($w->team->owner)->get(route('removed.index'))
            ->assertOk()
            ->assertSee('Nothing has been removed');
    }

    public function test_every_type_of_removed_record_is_listed_and_live_records_are_not(): void
    {
        $w = $this->world($this->teamWithOwner());
        $live = $this->world($w->team);
        $live->farm->update(['name' => 'Live Homestead Keepsake']);

        $w->farm->update(['name' => 'Removed Homestead Alpha']);
        $w->field->update(['name' => 'Removed Paddock Bravo']);
        $extraCrop = Crop::factory()->create(['team_id' => $w->team->id, 'name' => 'Lonely Crop Delta']);
        $extraCrop->delete();
        $w->farm->delete();

        $response = $this->actingAs($w->team->owner)->get(route('removed.index'));

        $response->assertOk()
            ->assertSee('Removed Homestead Alpha')
            ->assertSee('Removed Paddock Bravo')
            ->assertSee('Lonely Crop Delta')
            ->assertDontSee('Live Homestead Keepsake');

        $types = $response->viewData('items')->getCollection()->pluck('type')->unique()->sort()->values()->all();
        $this->assertSame(['crop', 'cycle', 'farm', 'field', 'harvest', 'planting'], $types);
    }

    public function test_records_beneath_a_removed_parent_are_not_hidden_by_it(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();

        $items = $this->actingAs($w->team->owner)->get(route('removed.index'))->viewData('items');

        $keys = $items->getCollection()->map(fn ($i) => $i->type.':'.$i->record->id)->all();
        foreach (['farm:'.$w->farm->id, 'field:'.$w->field->id, 'cycle:'.$w->cycle->id, 'planting:'.$w->planting->id, 'harvest:'.$w->harvest->id] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_the_newest_removal_comes_first(): void
    {
        $team = $this->teamWithOwner();
        $old = Farm::factory()->create(['team_id' => $team->id, 'name' => 'Removed Long Ago Farm']);
        $new = Farm::factory()->create(['team_id' => $team->id, 'name' => 'Removed Yesterday Farm']);
        Carbon::setTestNow('2026-03-01 10:00:00');
        $old->delete();
        Carbon::setTestNow('2026-03-09 10:00:00');
        $new->delete();
        Carbon::setTestNow();

        $this->actingAs($team->owner)->get(route('removed.index'))
            ->assertSeeInOrder(['Removed Yesterday Farm', 'Removed Long Ago Farm']);
    }

    public function test_the_type_filter_narrows_to_one_kind_of_record(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();

        $items = $this->actingAs($w->team->owner)->get(route('removed.index', ['type' => 'harvest']))->viewData('items');

        $this->assertSame(1, $items->total());
        $this->assertSame('harvest', $items->first()->type);
    }

    public function test_an_unknown_type_filter_is_ignored_rather_than_trusted(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();

        $items = $this->actingAs($w->team->owner)->get(route('removed.index', ['type' => 'users; drop table farms']))->viewData('items');

        $this->assertSame(5, $items->total());
    }

    public function test_search_matches_names_and_context_and_treats_percent_literally(): void
    {
        $team = $this->teamWithOwner();
        $a = Farm::factory()->create(['team_id' => $team->id, 'name' => 'Marula Ridge Estate']);
        $b = Farm::factory()->create(['team_id' => $team->id, 'name' => 'Baobab Flats']);
        $c = Farm::factory()->create(['team_id' => $team->id, 'name' => '100% Organic Plot']);
        $a->delete();
        $b->delete();
        $c->delete();

        $this->actingAs($team->owner);
        $this->assertSame(['Marula Ridge Estate'], $this->get(route('removed.index', ['q' => 'marula']))->viewData('items')->getCollection()->map(fn ($i) => $i->title)->all());
        $this->assertSame(['100% Organic Plot'], $this->get(route('removed.index', ['q' => '100%']))->viewData('items')->getCollection()->map(fn ($i) => $i->title)->all());
        $this->assertSame(1, $this->get(route('removed.index', ['q' => '%']))->viewData('items')->total());
    }

    public function test_search_finds_a_removed_crop_cycle_by_its_crop(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->crop->update(['name' => 'Amadumbe']);
        $w->cycle->delete();

        $items = $this->actingAs($w->team->owner)->get(route('removed.index', ['q' => 'amadumbe']))->viewData('items');

        $this->assertSame(['cycle'], $items->getCollection()->pluck('type')->all());
    }

    public function test_the_list_is_paginated(): void
    {
        $team = $this->teamWithOwner();
        Farm::factory()->count(23)->create(['team_id' => $team->id])->each->delete();

        $this->actingAs($team->owner);
        $first = $this->get(route('removed.index'))->viewData('items');
        $second = $this->get(route('removed.index', ['page' => 2]))->viewData('items');

        $this->assertSame(23, $first->total());
        $this->assertSame(20, $first->count());
        $this->assertSame(3, $second->count());
        $this->assertSame([], array_intersect(
            $first->getCollection()->map(fn ($i) => $i->record->id)->all(),
            $second->getCollection()->map(fn ($i) => $i->record->id)->all(),
        ));
    }

    public function test_a_child_whose_parent_is_removed_says_which_parent_to_restore_first(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->update(['name' => 'Kopje Home Farm']);
        $w->farm->delete();

        $response = $this->actingAs($w->team->owner)->get(route('removed.index', ['type' => 'field']));

        $response->assertSee('Restore the farm')->assertSee('Kopje Home Farm');
        $response->assertDontSee(route('removed.restore', ['type' => 'field', 'id' => $w->field->id]));
    }

    public function test_owners_and_admins_see_restore_controls_and_others_do_not(): void
    {
        $team = $this->teamWithOwner();
        $w = $this->world($team);
        $w->planting->delete();
        $url = route('removed.restore', ['type' => 'planting', 'id' => $w->planting->id]);

        foreach (['owner', 'admin'] as $role) {
            $this->actingAs($this->memberOf($team, $role))->get(route('removed.index'))->assertOk()->assertSee($url);
        }
        foreach (['editor', 'viewer'] as $role) {
            $this->actingAs($this->memberOf($team, $role))->get(route('removed.index'))->assertOk()->assertDontSee($url);
        }
    }

    public function test_the_sidebar_links_to_the_page(): void
    {
        $w = $this->world($this->teamWithOwner());

        $this->actingAs($w->team->owner)->get(route('dashboard'))->assertSee(route('removed.index'));
    }

    public function test_a_user_with_no_current_team_gets_a_clear_refusal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('removed.index'))->assertForbidden();
    }

    public function test_live_queries_do_not_see_a_removed_chain(): void
    {
        $w = $this->world($this->teamWithOwner());
        $w->farm->delete();

        foreach ([Farm::class, Field::class, CropCycle::class, PlantingRecord::class, HarvestRecord::class] as $model) {
            $this->assertSame(0, $model::query()->forTeam($w->team)->count(), "$model still visible to live queries");
        }
    }
}
