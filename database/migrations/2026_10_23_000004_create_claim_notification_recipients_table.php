<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_notification_recipients', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        $legacyEmails = collect(explode(',', (string) env('DTC_CLAIMS_NOTIFICATION_EMAIL', '')))
            ->map(fn (string $email) => strtolower(trim($email)))
            ->filter()
            ->all();

        if ($legacyEmails === []) {
            return;
        }

        $now = now();
        $recipients = User::query()
            ->get(['id', 'email'])
            ->filter(fn (User $user) => in_array(strtolower(trim((string) $user->email)), $legacyEmails, true))
            ->map(fn (User $user) => [
                'user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($recipients !== []) {
            DB::table('claim_notification_recipients')->insert($recipients);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_notification_recipients');
    }
};
