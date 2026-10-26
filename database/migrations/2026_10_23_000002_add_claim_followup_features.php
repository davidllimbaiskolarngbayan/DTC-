<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('claim_email_deliveries') && ! Schema::hasColumn('claim_email_deliveries', 'claim_history_id')) {
            Schema::table('claim_email_deliveries', function (Blueprint $table) {
                $table->foreignId('claim_history_id')->nullable()->constrained('claim_histories')->nullOnDelete();
            });
        }

        foreach ([
            'email_verified_at' => fn (Blueprint $table) => $table->timestamp('email_verified_at')->nullable(),
            'email_verification_token_hash' => fn (Blueprint $table) => $table->char('email_verification_token_hash', 64)->nullable(),
            'email_verification_expires_at' => fn (Blueprint $table) => $table->timestamp('email_verification_expires_at')->nullable(),
        ] as $column => $addColumn) {
            if (! Schema::hasColumn('dtc_users', $column)) {
                Schema::table('dtc_users', $addColumn);
            }
        }

        foreach ([
            'submission_notification_attempts' => fn (Blueprint $table) => $table->unsignedInteger('submission_notification_attempts')->default(0),
            'submission_notification_sent_at' => fn (Blueprint $table) => $table->timestamp('submission_notification_sent_at')->nullable(),
            'staff_notification_status' => fn (Blueprint $table) => $table->string('staff_notification_status', 32)->default('pending'),
            'staff_notification_error' => fn (Blueprint $table) => $table->text('staff_notification_error')->nullable(),
            'staff_notification_attempts' => fn (Blueprint $table) => $table->unsignedInteger('staff_notification_attempts')->default(0),
            'staff_notification_sent_at' => fn (Blueprint $table) => $table->timestamp('staff_notification_sent_at')->nullable(),
        ] as $column => $addColumn) {
            if (! Schema::hasColumn('claims', $column)) {
                Schema::table('claims', $addColumn);
            }
        }

        if (! Schema::hasTable('claim_attachments')) {
            Schema::create('claim_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
                $table->string('disk', 32)->default('local');
                $table->string('path', 512);
                $table->string('original_name', 255);
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('size');
                $table->timestamps();
                $table->index(['claim_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_attachments');

        $claimColumns = array_filter([
            'submission_notification_attempts',
            'submission_notification_sent_at',
            'staff_notification_status',
            'staff_notification_error',
            'staff_notification_attempts',
            'staff_notification_sent_at',
        ], fn (string $column) => Schema::hasColumn('claims', $column));
        if ($claimColumns) {
            Schema::table('claims', fn (Blueprint $table) => $table->dropColumn($claimColumns));
        }

        $dtcColumns = array_filter([
            'email_verified_at',
            'email_verification_token_hash',
            'email_verification_expires_at',
        ], fn (string $column) => Schema::hasColumn('dtc_users', $column));
        if ($dtcColumns) {
            Schema::table('dtc_users', fn (Blueprint $table) => $table->dropColumn($dtcColumns));
        }
    }
};
