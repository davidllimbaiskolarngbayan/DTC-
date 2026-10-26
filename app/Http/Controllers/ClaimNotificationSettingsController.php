<?php

namespace App\Http\Controllers;

use App\Models\DtcAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClaimNotificationSettingsController extends Controller
{
    public function index(): View
    {
        $staff = User::query()->where('role', 'staff')->orderBy('name')->get(['id', 'name', 'email']);
        $selectedStaffIds = DB::table('claim_notification_recipients')
            ->whereIn('user_id', $staff->pluck('id'))
            ->pluck('user_id')
            ->all();

        return view('settings.claim-notifications', compact('staff', 'selectedStaffIds'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipients' => ['nullable', 'array'],
            'recipients.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('role', 'staff'),
            ],
        ]);
        $recipientIds = array_map('intval', $data['recipients'] ?? []);

        DB::transaction(function () use ($recipientIds, $request) {
            DB::table('claim_notification_recipients')->delete();

            if ($recipientIds !== []) {
                $now = now();
                DB::table('claim_notification_recipients')->insert(array_map(
                    fn (int $userId) => [
                        'user_id' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $recipientIds
                ));
            }

            DtcAuditLog::record(
                $request,
                'claim.alert_recipients_updated',
                'settings',
                (int) $request->user()->getAuthIdentifier(),
                null,
                'Updated staff recipients for claim notifications.',
                null,
                ['selected_staff_ids' => $recipientIds]
            );
        });

        return redirect()->route('settings.claim-notifications')
            ->with('status', $recipientIds === []
                ? 'No staff recipients are selected. Claim alerts and reminders will not be sent.'
                : 'Staff claim notification recipients updated.');
    }
}
