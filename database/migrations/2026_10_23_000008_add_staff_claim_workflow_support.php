<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->index(['status', 'created_at']);
        });

        Schema::create('claim_staff_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 80);
            $table->string('message', 160);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'claim_id', 'event_key'], 'claim_staff_notification_unique');
            $table->index(['user_id', 'read_at', 'created_at'], 'claim_staff_notification_unread');
        });

        $staffIds = DB::table('users')->where('role', 'staff')->pluck('id');
        if ($staffIds->isEmpty()) {
            return;
        }

        $now = now();
        DB::table('claims')->where('status', 'submitted')->orderBy('id')->chunkById(100, function ($claims) use ($staffIds, $now) {
            $notifications = [];
            foreach ($claims as $claim) {
                foreach ($staffIds as $userId) {
                    $notifications[] = [
                        'claim_id' => $claim->id,
                        'user_id' => $userId,
                        'event_key' => 'claim_submitted',
                        'message' => 'New claim submitted',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($notifications !== []) {
                DB::table('claim_staff_notifications')->insert($notifications);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_staff_notifications');
        Schema::table('claims', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn('assigned_at');
        });
    }
};
