<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core agriculture domain tables for the Dot.Farms MVP.
 *
 * Modeled on the entities described in wiki.md §4 and Dot.Brain's
 * platforms/dot-farms.md §2: Farm (tenant root) -> Field/paddock ->
 * Crop cycle (field + season + crop, planting -> harvest lifecycle) ->
 * Planting/harvest log.
 *
 * `season` is a first-class column on `crop_cycles` rather than being
 * carried only in payload/event context. This resolves the "seasonal
 * scope fields" open question flagged in both wiki.md and Dot.Brain's
 * platform doc in favor of making season queryable and reportable
 * (e.g. "crops in season" on the dashboard) without parsing free text.
 *
 * `farms` carries `team_id` directly (the tenant boundary, matching the
 * `agriculture.<tenant>.<event>` topic scoping in wiki.md §7). `fields`,
 * `crop_cycles`, `planting_records`, and `harvest_records` are scoped by
 * their parent relationship (field -> farm -> team) rather than
 * duplicating `team_id` on every table, since none of them are ever
 * queried independently of their farm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->decimal('size_hectares', 10, 2)->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
        });

        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->string('name');
            $table->string('code'); // short paddock/field code, unique per farm
            $table->string('soil_type')->nullable();
            $table->string('moisture_zone')->nullable();
            $table->decimal('size_hectares', 10, 2)->nullable();
            $table->string('status')->default('active'); // active, fallow, retired
            $table->timestamps();

            $table->unique(['farm_id', 'code']);
            $table->index(['farm_id', 'status']);
        });

        Schema::create('crops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name'); // e.g. Maize, Wheat, Tomato
            $table->string('variety')->nullable();
            $table->string('category')->nullable(); // grain, vegetable, fruit, legume...
            $table->unsignedInteger('typical_cycle_days')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'name']);
        });

        Schema::create('crop_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('fields')->cascadeOnDelete();
            $table->foreignId('crop_id')->constrained('crops')->cascadeOnDelete();
            $table->string('season'); // e.g. "2026 Summer" — first-class, see note above
            $table->string('status')->default('planned'); // planned, planted, growing, harvested, failed
            $table->timestamp('planted_at')->nullable();
            $table->timestamp('expected_harvest_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['field_id', 'season']);
            $table->index(['field_id', 'status']);
        });

        Schema::create('planting_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_cycle_id')->constrained('crop_cycles')->cascadeOnDelete();
            $table->timestamp('planted_at');
            $table->decimal('quantity_planted', 12, 2)->nullable();
            $table->string('unit')->default('kg'); // kg, seeds, seedlings
            $table->string('method')->nullable(); // direct-seed, transplant, broadcast...
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('crop_cycle_id');
        });

        Schema::create('harvest_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_cycle_id')->constrained('crop_cycles')->cascadeOnDelete();
            $table->timestamp('harvested_at');
            $table->decimal('quantity_harvested', 12, 2);
            $table->string('unit')->default('kg');
            $table->string('quality_grade')->nullable(); // A, B, C / grower-defined
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('crop_cycle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_records');
        Schema::dropIfExists('planting_records');
        Schema::dropIfExists('crop_cycles');
        Schema::dropIfExists('crops');
        Schema::dropIfExists('fields');
        Schema::dropIfExists('farms');
    }
};
