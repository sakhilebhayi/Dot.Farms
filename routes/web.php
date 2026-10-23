<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\Farms\CropController;
use App\Http\Controllers\Farms\CropCycleController;
use App\Http\Controllers\Farms\CropCycleStatusController;
use App\Http\Controllers\Farms\DashboardController;
use App\Http\Controllers\Farms\FarmController;
use App\Http\Controllers\Farms\FieldController;
use App\Http\Controllers\Farms\HarvestExportController;
use App\Http\Controllers\Farms\HarvestRecordController;
use App\Http\Controllers\Farms\PlantingRecordController;
use App\Http\Controllers\Farms\RemovedRecordController;
use App\Http\Controllers\Farms\ReportController;
use App\Support\Farms\RemovedLookup;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

Route::post('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->withoutMiddleware(['Illuminate\Foundation\Http\Middleware\PreventRequestForgery', 'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken', 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken'])
    ->middleware('throttle:10,1')
    ->name('ecosystem.auth');

// Links minted before the token moved into a POST body carried it in the URL. They are no longer honoured:
// the visitor is sent to the normal sign-in page and the token in the URL is never read.
Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'legacy']);

Route::get('/', fn () => view('welcome'));

// Cookie Policy — Jetstream's termsAndPrivacyPolicy feature covers terms.show/policy.show
// natively. There's no Jetstream equivalent for a Cookie Policy, so this one is wired by hand,
// following the exact same Markdown-source convention.
Route::get('/cookies', function () {
    return view('cookies', [
        'cookies' => Str::markdown(file_get_contents(Jetstream::localizedMarkdownPath('cookies.md'))),
    ]);
})->name('cookies');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Farms
    Route::get('/farms', [FarmController::class, 'index'])->name('farms.index');
    Route::get('/farms/create', [FarmController::class, 'create'])->name('farms.create');
    Route::post('/farms', [FarmController::class, 'store'])->name('farms.store');

    // Everything nested under a farm binds each child through its parent
    // (scopeBindings), so /farms/1/fields/9 404s when field 9 is not in farm 1.
    Route::scopeBindings()->group(function () {
        Route::get('/farms/{farm}', [FarmController::class, 'show'])->name('farms.show');
        Route::get('/farms/{farm}/edit', [FarmController::class, 'edit'])->name('farms.edit');
        Route::put('/farms/{farm}', [FarmController::class, 'update'])->name('farms.update');
        Route::delete('/farms/{farm}', [FarmController::class, 'destroy'])->name('farms.destroy');

        // Fields (nested under a farm)
        Route::get('/farms/{farm}/fields/create', [FieldController::class, 'create'])->name('fields.create');
        Route::post('/farms/{farm}/fields', [FieldController::class, 'store'])->name('fields.store');
        Route::get('/farms/{farm}/fields/{field}', [FieldController::class, 'show'])->name('fields.show');
        Route::get('/farms/{farm}/fields/{field}/edit', [FieldController::class, 'edit'])->name('fields.edit');
        Route::put('/farms/{farm}/fields/{field}', [FieldController::class, 'update'])->name('fields.update');
        Route::delete('/farms/{farm}/fields/{field}', [FieldController::class, 'destroy'])->name('fields.destroy');

        // Crop cycles (nested under a field)
        $cycle = '/farms/{farm}/fields/{field}/crop-cycles';
        Route::get("$cycle/create", [CropCycleController::class, 'create'])->name('crop-cycles.create');
        Route::post($cycle, [CropCycleController::class, 'store'])->name('crop-cycles.store');
        Route::get("$cycle/{cropCycle}", [CropCycleController::class, 'show'])->name('crop-cycles.show');
        Route::get("$cycle/{cropCycle}/edit", [CropCycleController::class, 'edit'])->name('crop-cycles.edit');
        Route::put("$cycle/{cropCycle}", [CropCycleController::class, 'update'])->name('crop-cycles.update');
        Route::delete("$cycle/{cropCycle}", [CropCycleController::class, 'destroy'])->name('crop-cycles.destroy');
        Route::post("$cycle/{cropCycle}/transition", [CropCycleStatusController::class, 'store'])->name('crop-cycles.transition');

        // Plantings and harvests recorded against a cycle
        Route::post("$cycle/{cropCycle}/planting", [PlantingRecordController::class, 'store'])->name('crop-cycles.planting.store');
        Route::get("$cycle/{cropCycle}/plantings/{plantingRecord}/edit", [PlantingRecordController::class, 'edit'])->name('plantings.edit');
        Route::put("$cycle/{cropCycle}/plantings/{plantingRecord}", [PlantingRecordController::class, 'update'])->name('plantings.update');
        Route::delete("$cycle/{cropCycle}/plantings/{plantingRecord}", [PlantingRecordController::class, 'destroy'])->name('plantings.destroy');
        Route::post("$cycle/{cropCycle}/harvest", [HarvestRecordController::class, 'store'])->name('crop-cycles.harvest.store');
    });

    // Crops (team-wide catalog)
    Route::get('/crops', [CropController::class, 'index'])->name('crops.index');
    Route::get('/crops/create', [CropController::class, 'create'])->name('crops.create');
    Route::post('/crops', [CropController::class, 'store'])->name('crops.store');
    Route::get('/crops/{crop}/edit', [CropController::class, 'edit'])->name('crops.edit');
    Route::put('/crops/{crop}', [CropController::class, 'update'])->name('crops.update');
    Route::delete('/crops/{crop}', [CropController::class, 'destroy'])->name('crops.destroy');

    // Harvest records (team-wide list, CSV export, correction)
    Route::get('/harvests', [HarvestRecordController::class, 'index'])->name('harvests.index');
    Route::get('/harvests/export', HarvestExportController::class)->name('harvests.export');
    Route::get('/harvests/{harvestRecord}', [HarvestRecordController::class, 'show'])->name('harvests.show');
    Route::get('/harvests/{harvestRecord}/edit', [HarvestRecordController::class, 'edit'])->name('harvests.edit');
    Route::put('/harvests/{harvestRecord}', [HarvestRecordController::class, 'update'])->name('harvests.update');
    Route::delete('/harvests/{harvestRecord}', [HarvestRecordController::class, 'destroy'])->name('harvests.destroy');

    // Removed records (soft-deleted farms, fields, crops, cycles, plantings, harvests): list and restore
    Route::get('/removed', [RemovedRecordController::class, 'index'])->name('removed.index');
    Route::post('/removed/{type}/{id}/restore', [RemovedRecordController::class, 'restore'])
        ->whereIn('type', array_keys(RemovedLookup::TYPES))
        ->whereNumber('id')
        ->name('removed.restore');
});

// Public sign-up is closed (accounts are created by InfoDot at first sign-in). Keep the named route so any link or
// invitation email that points at it lands on the login page instead of failing.
if (! Features::enabled(Features::registration())) {
    Route::redirect('/register', '/login')->name('register');
}
