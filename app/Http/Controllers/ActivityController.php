<?php

namespace App\Http\Controllers;

use App\Models\DtcAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:registrant,claim'],
            'date_from' => ['nullable', 'date', 'before_or_equal:'.now(config('dtc.timezone'))->toDateString()],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'before_or_equal:'.now(config('dtc.timezone'))->toDateString()],
        ]);

        $activities = DtcAuditLog::query()
            ->with(['user', 'registrant', 'claim'])
            ->whereNotNull('summary')
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('entity_type', $type))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $term = '%'.addcslashes(trim($search), '%_\\').'%';
                $query->where(function ($query) use ($term, $search) {
                    $query->where('summary', 'like', $term)
                        ->orWhere('action', 'like', $term)
                        ->orWhere('entity_id', is_numeric($search) ? (int) $search : -1)
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $term))
                        ->orWhereHas('registrant', fn ($registrant) => $registrant
                            ->where('dtc_card_no', 'like', $term)
                            ->orWhere('vehicle_reg_id', 'like', $term))
                        ->orWhereHas('claim', fn ($claim) => $claim->where('claim_number', 'like', $term));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('activity.index', [
            'activities' => $activities,
            'types' => ['registrant' => 'Registrant', 'claim' => 'Claim'],
        ]);
    }
}
