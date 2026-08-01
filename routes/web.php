<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\Farms\CropController;
use App\Http\Controllers\Farms\CropCycleController;
use App\Http\Controllers\Farms\FarmController;
use App\Http\Controllers\Farms\FieldController;
use App\Http\Controllers\Farms\HarvestRecordController;
use App\Http\Controllers\Farms\PlantingRecordController;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use Illuminate\Support\Facades\Route;

Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->name('ecosystem.auth');

Route::get('/', fn () => view('welcome'));

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        $team = auth()->user()->currentTeam;

        $farmIds = Farm::where('team_id', $team->id)->pluck('id');
        $fieldIds = Field::whereIn('farm_id', $farmIds)->pluck('id');

        return view('dashboard', [
            'farmCount' => $farmIds->count(),
            'activeFieldCount' => Field::whereIn('farm_id', $farmIds)->where('status', 'active')->count(),
            'cropsInSeasonCount' => CropCycle::whereIn('field_id', $fieldIds)
                ->whereIn('status', [CropCycle::STATUS_PLANTED, CropCycle::STATUS_GROWING])
                ->count(),
            'recentHarvests' => HarvestRecord::whereHas(
                'cropCycle.field',
                fn ($q) => $q->whereIn('farm_id', $farmIds)
            )->with(['cropCycle.crop', 'cropCycle.field.farm'])
                ->latest('harvested_at')
                ->take(6)
                ->get(),
        ]);
    })->name('dashboard');

    // Farms
    Route::get('/farms', [FarmController::class, 'index'])->name('farms.index');
    Route::get('/farms/create', [FarmController::class, 'create'])->name('farms.create');
    Route::post('/farms', [FarmController::class, 'store'])->name('farms.store');
    Route::get('/farms/{farm}', [FarmController::class, 'show'])->name('farms.show');
    Route::get('/farms/{farm}/edit', [FarmController::class, 'edit'])->name('farms.edit');
    Route::put('/farms/{farm}', [FarmController::class, 'update'])->name('farms.update');

    // Fields (nested under a farm)
    Route::get('/farms/{farm}/fields/create', [FieldController::class, 'create'])->name('fields.create');
    Route::post('/farms/{farm}/fields', [FieldController::class, 'store'])->name('fields.store');
    Route::get('/farms/{farm}/fields/{field}', [FieldController::class, 'show'])->name('fields.show');

    // Crop cycles (nested under a field)
    Route::get('/farms/{farm}/fields/{field}/crop-cycles/create', [CropCycleController::class, 'create'])->name('crop-cycles.create');
    Route::post('/farms/{farm}/fields/{field}/crop-cycles', [CropCycleController::class, 'store'])->name('crop-cycles.store');
    Route::get('/farms/{farm}/fields/{field}/crop-cycles/{cropCycle}', [CropCycleController::class, 'show'])->name('crop-cycles.show');
    Route::post('/farms/{farm}/fields/{field}/crop-cycles/{cropCycle}/planting', [PlantingRecordController::class, 'store'])->name('crop-cycles.planting.store');
    Route::post('/farms/{farm}/fields/{field}/crop-cycles/{cropCycle}/harvest', [HarvestRecordController::class, 'store'])->name('crop-cycles.harvest.store');

    // Crops (team-wide catalog)
    Route::get('/crops', [CropController::class, 'index'])->name('crops.index');
    Route::get('/crops/create', [CropController::class, 'create'])->name('crops.create');
    Route::post('/crops', [CropController::class, 'store'])->name('crops.store');

    // Harvest records (team-wide list)
    Route::get('/harvests', [HarvestRecordController::class, 'index'])->name('harvests.index');
    Route::get('/harvests/{harvestRecord}', [HarvestRecordController::class, 'show'])->name('harvests.show');
});
