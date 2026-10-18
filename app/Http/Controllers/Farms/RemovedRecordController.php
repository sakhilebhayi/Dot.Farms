<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Farms\RemovedRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The Removed screen: every soft-deleted farm, field, crop, crop cycle, planting
 * and harvest record of the current team, with a restore action for owners and
 * admins. Rules and tenancy live in RemovedRecords.
 */
class RemovedRecordController extends Controller
{
    private function team(Request $request): Team
    {
        return $request->user()->currentTeam ?? abort(403, 'No active team selected.');
    }

    public function index(Request $request, RemovedRecords $removed): View
    {
        $team = $this->team($request);
        $type = RemovedRecords::isType($request->query('type')) ? $request->query('type') : null;
        $search = $this->search($request->query('q'));

        return view('removed.index', [
            'items' => $removed->page($team, $type, $search)->withQueryString(),
            'counts' => $removed->counts($team),
            'type' => $type,
            'search' => $search,
            'retentionDays' => (int) config('farms.removed_retention_days'),
            'canRestore' => $request->user()->hasTeamRole($team, 'admin'),
        ]);
    }

    public function restore(Request $request, string $type, int $id, RemovedRecords $removed): RedirectResponse
    {
        $team = $this->team($request);

        // Not found for this team (another company's id, a live record, a purged one) is a 404, not a 403:
        // the record is invisible to the caller, not merely forbidden.
        $record = $removed->find($type, $id, $team) ?? abort(404);

        Gate::authorize('restore', $record);

        $result = $removed->restore($record, $type, $team, $request->user());

        return redirect()
            ->route('removed.index', array_filter([
                'type' => RemovedRecords::isType($request->input('back_type')) ? $request->input('back_type') : null,
                'q' => $this->search($request->input('back_q')),
            ]))
            ->with('flash.banner', $result->message());
    }

    private function search(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : mb_substr($value, 0, 100);
    }
}
