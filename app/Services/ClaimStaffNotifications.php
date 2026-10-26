<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\ClaimStaffNotification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ClaimStaffNotifications
{
    public function notify(Claim $claim, string $eventKey, string $message): void
    {
        $now = now();
        $notifications = DB::table('users')
            ->where('role', 'staff')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn (int $userId) => [
                'claim_id' => $claim->id,
                'user_id' => $userId,
                'event_key' => $eventKey,
                'message' => $message,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($notifications !== []) {
            DB::table('claim_staff_notifications')->insertOrIgnore($notifications);
        }
    }

    public function unreadFor(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return ClaimStaffNotification::query()
            ->with('claim:id,claim_number,subject')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->latest('id')
            ->limit(6)
            ->get();
    }

    public function unreadCountFor(User $user): int
    {
        return ClaimStaffNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function syncOutstandingSubmissionsFor(User $user): void
    {
        if ($user->role !== 'staff') {
            return;
        }

        Claim::query()
            ->where('status', 'submitted')
            ->orderBy('id')
            ->chunkById(500, function ($claims) use ($user) {
                $now = Carbon::now();
                $notifications = $claims->map(fn (Claim $claim) => [
                    'claim_id' => $claim->id,
                    'user_id' => $user->id,
                    'event_key' => 'claim_submitted',
                    'message' => 'New claim submitted',
                    'created_at' => $claim->created_at ?? $now,
                    'updated_at' => $now,
                ])->all();

                if ($notifications !== []) {
                    DB::table('claim_staff_notifications')->insertOrIgnore($notifications);
                }
            });
    }

    public function markClaimRead(User $user, Claim $claim): void
    {
        ClaimStaffNotification::query()
            ->where('user_id', $user->id)
            ->where('claim_id', $claim->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }
}
