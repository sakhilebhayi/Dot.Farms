<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Services\Farms\CropCycleLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The explicit lifecycle actions on a crop cycle: mark growing, close as
 * harvested, mark failed or abandoned (with a reason), re-open. Every rule
 * lives in CropCycleLifecycle; this only authorises, validates the request
 * shape and reports the outcome.
 */
class CropCycleStatusController extends Controller
{
    private const MESSAGES = [
        'growing' => 'Marked growing.',
        'close' => 'Cycle closed as harvested.',
        'fail' => 'Cycle marked failed.',
        'abandon' => 'Cycle marked abandoned.',
        'reopen' => 'Cycle re-opened.',
    ];

    public function store(Request $request, Farm $farm, Field $field, CropCycle $cropCycle, CropCycleLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('transition', $cropCycle);

        $validated = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::MESSAGES))],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        match ($validated['action']) {
            'growing' => $lifecycle->markGrowing($cropCycle),
            'close' => $lifecycle->close($cropCycle),
            'fail' => $lifecycle->fail($cropCycle, $validated['reason'] ?? null),
            'abandon' => $lifecycle->abandon($cropCycle, $validated['reason'] ?? null),
            'reopen' => $lifecycle->reopen($cropCycle),
        };

        return redirect()
            ->route('crop-cycles.show', [$farm, $field, $cropCycle])
            ->with('flash.banner', self::MESSAGES[$validated['action']]);
    }
}
