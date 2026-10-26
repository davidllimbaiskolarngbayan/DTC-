<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClaimNotificationRecipients
{
    /**
     * @return list<string>
     */
    public function emails(): array
    {
        $userIds = DB::table('claim_notification_recipients')->pluck('user_id');
        if ($userIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->where('role', 'staff')
            ->orderBy('id')
            ->get(['id', 'email'])
            ->map(fn (User $user) => trim((string) $user->email))
            ->filter()
            ->values()
            ->all();
    }
}
