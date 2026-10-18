<?php

namespace Tests\Feature\Farms;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises the farm-operations migration for real: rolls it back, loads rows
 * the way the old app wrote them, migrates forward again and checks what
 * happened to them. Uses full migrations rather than a wrapping transaction
 * because it needs to run DDL.
 */
class FarmOperationsMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_the_reporting_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasIndex('harvest_records', ['harvested_at']));
        $this->assertTrue(Schema::hasIndex('harvest_records', ['crop_cycle_id', 'harvested_at']));
        $this->assertTrue(Schema::hasIndex('crop_cycles', ['status']));
        $this->assertTrue(Schema::hasIndex('crop_cycles', ['season']));
        $this->assertTrue(Schema::hasIndex('crop_cycles', ['status', 'expected_harvest_at']));
        $this->assertTrue(Schema::hasIndex('planting_records', ['crop_cycle_id', 'planted_at']));
    }

    public function test_soft_delete_and_lifecycle_columns_exist(): void
    {
        foreach (['farms', 'fields', 'crops', 'crop_cycles', 'planting_records', 'harvest_records'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'deleted_at'), $table);
        }
        $this->assertTrue(Schema::hasColumns('crop_cycles', ['growing_at', 'closed_at', 'end_reason']));
    }

    public function test_rolling_back_and_forward_normalises_legacy_units_and_keeps_unknown_ones(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertFalse(Schema::hasColumn('crop_cycles', 'closed_at'));

        $teamId = DB::table('users')->insertGetId(['name' => 'U', 'email' => 'u@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $teamId = DB::table('teams')->insertGetId(['user_id' => $teamId, 'name' => 'T', 'personal_team' => true, 'created_at' => now(), 'updated_at' => now()]);
        $farm = DB::table('farms')->insertGetId(['team_id' => $teamId, 'name' => 'F', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $field = DB::table('fields')->insertGetId(['farm_id' => $farm, 'name' => 'N', 'code' => 'C1', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $crop = DB::table('crops')->insertGetId(['team_id' => $teamId, 'name' => 'Maize', 'created_at' => now(), 'updated_at' => now()]);
        $cycle = DB::table('crop_cycles')->insertGetId(['field_id' => $field, 'crop_id' => $crop, 'season' => 'S', 'status' => 'growing', 'created_at' => now(), 'updated_at' => now()]);

        foreach (['ton', 'Tons', 'crates', 'kg', 'sack'] as $unit) {
            DB::table('harvest_records')->insert(['crop_cycle_id' => $cycle, 'harvested_at' => now(), 'quantity_harvested' => 1, 'unit' => $unit, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['seeds', 'seedlings', 'kg'] as $unit) {
            DB::table('planting_records')->insert(['crop_cycle_id' => $cycle, 'planted_at' => now(), 'quantity_planted' => 1, 'unit' => $unit, 'created_at' => now(), 'updated_at' => now()]);
        }

        Artisan::call('migrate');

        $this->assertEqualsCanonicalizing(['t', 't', 'crate', 'kg', 'sack'], DB::table('harvest_records')->pluck('unit')->all());
        $this->assertEqualsCanonicalizing(['seed', 'seedling', 'kg'], DB::table('planting_records')->pluck('unit')->all());
        $this->assertSame('growing', DB::table('crop_cycles')->value('status'));
        $this->assertTrue(Schema::hasIndex('harvest_records', ['harvested_at']));
    }

    public function test_rolling_back_removes_soft_deleted_rows_instead_of_resurrecting_them(): void
    {
        $userId = DB::table('users')->insertGetId(['name' => 'U', 'email' => 'w@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $teamId = DB::table('teams')->insertGetId(['user_id' => $userId, 'name' => 'T', 'personal_team' => true, 'created_at' => now(), 'updated_at' => now()]);
        $stamp = ['created_at' => now(), 'updated_at' => now()];
        DB::table('farms')->insert(['team_id' => $teamId, 'name' => 'Deleted farm', 'status' => 'active', 'deleted_at' => now(), ...$stamp]);
        DB::table('farms')->insert(['team_id' => $teamId, 'name' => 'Live farm', 'status' => 'active', ...$stamp]);

        Artisan::call('migrate:rollback', ['--step' => 1]);

        $this->assertSame(['Live farm'], DB::table('farms')->pluck('name')->all());
        Artisan::call('migrate');
    }

    public function test_a_field_code_is_unique_only_among_live_fields(): void
    {
        $userId = DB::table('users')->insertGetId(['name' => 'U', 'email' => 'v@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $teamId = DB::table('teams')->insertGetId(['user_id' => $userId, 'name' => 'T', 'personal_team' => true, 'created_at' => now(), 'updated_at' => now()]);
        $farm = DB::table('farms')->insertGetId(['team_id' => $teamId, 'name' => 'F', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $row = ['farm_id' => $farm, 'name' => 'N', 'code' => 'DUP', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];

        DB::table('fields')->insert([...$row, 'deleted_at' => now()]);
        DB::table('fields')->insert($row);

        $this->assertSame(2, DB::table('fields')->where('code', 'DUP')->count());

        $this->expectException(QueryException::class);
        DB::table('fields')->insert($row);
    }
}
