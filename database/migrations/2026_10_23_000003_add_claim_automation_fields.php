<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('claims', 'follow_up_reminded_at')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->timestamp('follow_up_reminded_at')->nullable();
            });
        }

        foreach ([
            'retry_attempts' => fn (Blueprint $table) => $table->unsignedTinyInteger('retry_attempts')->default(0),
            'next_attempt_at' => fn (Blueprint $table) => $table->timestamp('next_attempt_at')->nullable(),
            'last_attempt_at' => fn (Blueprint $table) => $table->timestamp('last_attempt_at')->nullable(),
        ] as $column => $addColumn) {
            if (! Schema::hasColumn('claim_email_deliveries', $column)) {
                Schema::table('claim_email_deliveries', $addColumn);
            }
        }
    }

    public function down(): void
    {
        $deliveryColumns = array_filter(
            ['retry_attempts', 'next_attempt_at', 'last_attempt_at'],
            fn (string $column) => Schema::hasColumn('claim_email_deliveries', $column)
        );
        if ($deliveryColumns) {
            Schema::table('claim_email_deliveries', fn (Blueprint $table) => $table->dropColumn($deliveryColumns));
        }

        if (Schema::hasColumn('claims', 'follow_up_reminded_at')) {
            Schema::table('claims', fn (Blueprint $table) => $table->dropColumn('follow_up_reminded_at'));
        }
    }
};
