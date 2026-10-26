<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('claims', 'submission_notification_status')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->string('submission_notification_status', 32)->default('pending');
            });
        }

        if (! Schema::hasColumn('claims', 'submission_notification_error')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->text('submission_notification_error')->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = array_filter([
            Schema::hasColumn('claims', 'submission_notification_status')
                ? 'submission_notification_status'
                : null,
            Schema::hasColumn('claims', 'submission_notification_error')
                ? 'submission_notification_error'
                : null,
        ]);

        if ($columns) {
            Schema::table('claims', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};