<?php

namespace App\Http\Controllers;

use App\Contracts\DtcMarket;
use App\Models\Claim;
use App\Models\Dtc;
use App\Models\DtcAuditLog;
use App\Models\DtcCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DtcController extends Controller
{
    private const EXPORT_HEADERS = [
        'First Name', 'Last Name', 'Email Address', 'Contact number', 'Vehicle Reg ID', 'Brand', 'Model',
        'Quantity', 'Date of Purchase', 'Store Name', 'State', 'Tyre', 'Tyre Size', 'Rim Size', 'Mileage',
        'Is DTC', 'Subscribe', 'Created At', 'DTC Code',
    ];

    public function __construct(protected DtcMarket $dtcMarket)
    {
    }

    public function index()
    {
        return view('dashboard', [
            'total' => Dtc::count(),
            'recent' => Dtc::where('created_at', '>=', now()->subDays(30))->count(),
            'subscribed' => Dtc::where('subscribe', true)->whereNotNull('email_hash')->count(),
            'recentActivities' => DtcAuditLog::with(['user', 'registrant', 'claim'])
                ->whereNotNull('summary')
                ->latest('id')
                ->limit(8)
                ->get(),
            'charts' => [
                'monthly' => $this->registrationsPerMonth(),
                'tyres' => $this->topCounts('tyre'),
                'stores' => $this->topCounts('store_name'),
                'claims' => $this->claimStatusCounts(),
            ],
        ]);
    }

    public function lists(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        $users = Dtc::search($filters['search'] ?? null)->latest('id')->paginate(10)->withQueryString();

        return view('dtc.list', compact('users'));
    }

    public function show(Dtc $dtc)
    {
        [$warrantyEligible, $warrantyEligibilityReason] = \App\Models\Claim::eligibilityFor($dtc);
        $user = $dtc->load(['claims', 'promos']);

        return view('dtc.user-details', [
            'user' => $user,
            'warrantyEligible' => $warrantyEligible,
            'warrantyEligibilityReason' => $warrantyEligibilityReason,
        ]);
    }

    public function downloadPromoInvoice(Dtc $dtc)
    {
        $path = $dtc->promos()->where('promo_key', 'invoice')->value('promo_value');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $mimeType = Storage::disk('local')->mimeType($path);
        abort_unless(in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function edit(Dtc $dtc)
    {
        return view('dtc.user-edit', ['user' => $dtc]);
    }

    public function update(Request $request, Dtc $dtc)
    {
        $request->merge(['dtc_card_no' => strtoupper(trim((string) $request->input('dtc_card_no')))]);
        $request->validate(
            $this->dtcMarket->updateRules($dtc->id, $dtc->dtc_card_no),
            [],
            ['dtc_card_no' => 'DTC Code']
        );

        $before = $dtc->getAttributes();
        $this->dtcMarket->setUser($request->all());
        $user = Arr::only($this->dtcMarket->getUser(), $this->dtcMarket->editableFields());
        $updatedDtc = $dtc->updateUser($user + ['id' => $dtc->id]);
        DtcCode::where('code', $user['dtc_card_no'])->update(['is_use' => true]);
        $changedFields = array_values(array_filter(
            $this->dtcMarket->editableFields(),
            fn ($field) => (string) ($before[$field] ?? '') !== (string) ($updatedDtc->getAttributes()[$field] ?? '')
        ));
        if ($changedFields) {
            $fieldLabels = [
                'firstname' => 'First name', 'lastname' => 'Last name', 'email' => 'Email',
                'contact_no' => 'Contact number', 'vehicle_reg_id' => 'Registration ID',
                'brand' => 'Brand', 'model' => 'Model', 'tyre' => 'Tyre', 'tyre_size' => 'Tyre size',
                'rim_size' => 'Rim size', 'quantity' => 'Quantity', 'date_of_purchase' => 'Purchase date',
                'store_name' => 'Store', 'state' => 'State', 'mileage' => 'Mileage', 'dtc_card_no' => 'DTC Code',
            ];
            $changedLabels = array_map(fn ($field) => $fieldLabels[$field] ?? $field, $changedFields);
            DtcAuditLog::record(
                $request,
                'registrant.updated',
                'registrant',
                $dtc->id,
                $dtc->id,
                'Updated registrant details: '.implode(', ', $changedLabels),
                ['changed_fields' => $changedFields]
            );
        }

        return redirect()->route('dtc.show', $dtc)->with('status', 'Registrant updated.');
    }

    public function destroy(Request $request, Dtc $dtc)
    {
        DB::transaction(function () use ($request, $dtc) {
            $dtc->delete();
            DtcAuditLog::record(
                $request,
                'registrant.deleted',
                'registrant',
                $dtc->id,
                $dtc->id,
                'Deleted registrant record'
            );
        });

        return redirect()->route('dtc.lists')->with('status', 'Registrant deleted.');
    }

    public function downloadDtc(Request $request)
    {
        $data = $request->validate([
            'date_from' => ['required', 'date', 'before_or_equal:today'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from', 'before_or_equal:today'],
        ]);

        $from = Carbon::parse($data['date_from'])->startOfDay();
        $to = Carbon::parse($data['date_to'])->endOfDay();

        return response()->stream(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding

            fputcsv($out, self::EXPORT_HEADERS);

            Dtc::whereBetween('created_at', [$from, $to])->chunkById(100, function ($records) use ($out) {
                foreach ($records as $record) {
                    fputcsv($out, array_map([$this, 'safeCell'], $this->exportRow($record)));
                }
                if (ob_get_length()) {
                    ob_flush();
                }
                flush();
            });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="dtc_users_'.date('Y-m-d_His').'.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function exportRow(Dtc $record): array
    {
        return [
            $record->firstname, $record->lastname, $record->email, $record->contact_no, $record->vehicle_reg_id,
            $record->brand, $record->model, $record->quantity, $record->date_of_purchase?->format('Y-m-d'),
            $record->store_name, $record->state, $record->tyre, $record->tyre_size, $record->rim_size,
            $record->mileage, (int) $record->is_dtc, (int) $record->subscribe,
            $record->created_at?->format('Y-m-d H:i:s'), $record->dtc_card_no,
        ];
    }

    // Prevents spreadsheet formula injection from stored values.
    private function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /** Last 12 months including empty ones; grouped in PHP to stay database-agnostic. */
    private function registrationsPerMonth(): array
    {
        $start = now()->startOfMonth()->subMonths(11);

        $counts = Dtc::where('date_of_purchase', '>=', $start)
            ->pluck('date_of_purchase')
            ->countBy(fn ($date) => $date->format('Y-m'));

        $labels = [];
        $data = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $labels[] = $month->format('M Y');
            $data[] = $counts->get($month->format('Y-m'), 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function topCounts(string $column, int $limit = 5): array
    {
        $rows = Dtc::whereNotNull($column)
            ->selectRaw("{$column} as label, count(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return ['labels' => $rows->pluck('label')->all(), 'data' => $rows->pluck('total')->all()];
    }

    private function claimStatusCounts(): array
    {
        $counts = Claim::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'labels' => array_values(Claim::STATUSES),
            'data' => array_map(
                fn (string $status): int => (int) $counts->get($status, 0),
                array_keys(Claim::STATUSES)
            ),
        ];
    }
}
