<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Farm operations: soft deletes, the crop-cycle lifecycle facts, standard
 * units and the indexes the reports and the dashboard lean on.
 *
 * Written to be portable across SQLite (tests) and PostgreSQL (production):
 * only Blueprint calls plus one partial unique index, whose
 * `CREATE UNIQUE INDEX ... WHERE` syntax both engines accept.
 *
 * What it does not do: it does not change the `crops` -> `crop_cycles` foreign
 * key (still ON DELETE CASCADE at the database level; SQLite cannot alter a
 * foreign key in place). The guard that stops a crop in use from being
 * removed lives in App\Models\Crop, which refuses both delete() and
 * forceDelete() while cycles reference it.
 */
return new class extends Migration
{
    private const SOFT_DELETE_TABLES = ['farms', 'fields', 'crops', 'crop_cycles', 'planting_records', 'harvest_records'];

    public function up(): void
    {
        foreach (self::SOFT_DELETE_TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->softDeletes());
        }

        Schema::table('crop_cycles', function (Blueprint $table) {
            $table->timestamp('growing_at')->nullable()->after('planted_at');
            $table->timestamp('closed_at')->nullable()->after('expected_harvest_at');
            $table->text('end_reason')->nullable()->after('closed_at');

            $table->index('status');
            $table->index('season');
            $table->index(['status', 'expected_harvest_at']);
        });

        Schema::table('harvest_records', function (Blueprint $table) {
            $table->index('harvested_at');
            $table->index(['crop_cycle_id', 'harvested_at']);
        });

        Schema::table('planting_records', function (Blueprint $table) {
            $table->index(['crop_cycle_id', 'planted_at']);
        });

        // A deleted field must not keep its code reserved: uniqueness now only
        // applies to live rows.
        Schema::table('fields', fn (Blueprint $table) => $table->dropUnique(['farm_id', 'code']));
        DB::statement('CREATE UNIQUE INDEX fields_farm_id_code_live_unique ON fields (farm_id, code) WHERE deleted_at IS NULL');

        $this->normaliseLegacyUnits();
    }

    public function down(): void
    {
        // Dropping deleted_at would silently bring every soft-deleted row back to
        // life (a deleted farm reappearing is worse than it staying deleted), so
        // rows that were deleted are removed for good first, children before parents.
        foreach (['harvest_records', 'planting_records', 'crop_cycles', 'fields', 'crops', 'farms'] as $name) {
            DB::table($name)->whereNotNull('deleted_at')->delete();
        }

        DB::statement('DROP INDEX fields_farm_id_code_live_unique');
        Schema::table('fields', fn (Blueprint $table) => $table->unique(['farm_id', 'code']));

        Schema::table('planting_records', fn (Blueprint $table) => $table->dropIndex(['crop_cycle_id', 'planted_at']));

        Schema::table('harvest_records', function (Blueprint $table) {
            $table->dropIndex(['crop_cycle_id', 'harvested_at']);
            $table->dropIndex(['harvested_at']);
        });

        Schema::table('crop_cycles', function (Blueprint $table) {
            $table->dropIndex(['status', 'expected_harvest_at']);
            $table->dropIndex(['season']);
            $table->dropIndex(['status']);
            $table->dropColumn(['growing_at', 'closed_at', 'end_reason']);
        });

        foreach (self::SOFT_DELETE_TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropSoftDeletes());
        }

        // Unit values are not rewritten back: 't' and 'crate' are still valid
        // free text, and the original spellings were never a single value.
    }

    /**
     * Rewrite the free-text spellings the old forms and factories produced
     * ('ton', 'crates', 'seeds', 'seedlings') to the standard unit codes.
     * Values that match none of these are left exactly as entered; the totals
     * show them in a bucket of their own instead of guessing.
     */
    private function normaliseLegacyUnits(): void
    {
        $map = [
            't' => ['ton', 'tons', 'tonne', 'tonnes'],
            'crate' => ['crates'],
            'seed' => ['seeds'],
            'seedling' => ['seedlings'],
        ];

        foreach (['harvest_records', 'planting_records'] as $table) {
            foreach ($map as $standard => $legacy) {
                DB::table($table)
                    ->whereIn(DB::raw('lower(trim(unit))'), $legacy)
                    ->update(['unit' => $standard]);
            }
        }
    }
};
