<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'entity_type' => fn (Blueprint $table) => $table->string('entity_type', 32)->nullable(),
            'entity_id' => fn (Blueprint $table) => $table->unsignedBigInteger('entity_id')->nullable(),
            'summary' => fn (Blueprint $table) => $table->string('summary', 255)->nullable(),
            'old_values' => fn (Blueprint $table) => $table->json('old_values')->nullable(),
            'new_values' => fn (Blueprint $table) => $table->json('new_values')->nullable(),
            'ip_address' => fn (Blueprint $table) => $table->string('ip_address', 45)->nullable(),
            'user_agent' => fn (Blueprint $table) => $table->text('user_agent')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('dtc_audit_logs', $name)) {
                Schema::table('dtc_audit_logs', $definition);
            }
        }

        if (! Schema::hasIndex('dtc_audit_logs', ['entity_type', 'created_at'])) {
            Schema::table('dtc_audit_logs', function (Blueprint $table) {
                $table->index(['entity_type', 'created_at']);
            });
        }

        $statuses = [
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'need_more_info' => 'Need More Info',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'resolved' => 'Resolved',
        ];

        DB::table('claim_histories as history')
            ->join('claims', 'claims.id', '=', 'history.claim_id')
            ->select([
                'history.id',
                'history.changed_by',
                'history.event_type',
                'history.from_status',
                'history.to_status',
                'history.created_at',
                'claims.id as claim_id',
                'claims.dtc_id',
                'claims.claim_number',
            ])
            ->orderBy('history.id')
            ->chunk(500, function ($histories) use ($statuses) {
                $rows = [];
                foreach ($histories as $history) {
                    $action = match ($history->event_type) {
                        'submitted' => 'claim.submitted',
                        'status_changed' => 'claim.status_changed',
                        'note_added' => 'claim.note_added',
                        default => 'claim.updated',
                    };

                    $summary = match ($history->event_type) {
                        'submitted' => $history->changed_by
                            ? 'Submitted claim '.$history->claim_number
                            : 'Customer submitted claim '.$history->claim_number,
                        'status_changed' => 'Updated claim '.$history->claim_number.' status: '.
                            ($statuses[$history->from_status] ?? $history->from_status).' to '.
                            ($statuses[$history->to_status] ?? $history->to_status),
                        'note_added' => 'Added an internal note to claim '.$history->claim_number,
                        default => 'Updated claim '.$history->claim_number,
                    };

                    $rows[] = [
                        'dtc_id' => $history->dtc_id,
                        'entity_type' => 'claim',
                        'entity_id' => $history->claim_id,
                        'user_id' => $history->changed_by,
                        'action' => $action,
                        'summary' => $summary,
                        'old_values' => $history->from_status
                            ? json_encode(['status' => $history->from_status])
                            : null,
                        'new_values' => $history->to_status
                            ? json_encode(['status' => $history->to_status])
                            : null,
                        'created_at' => $history->created_at,
                        'updated_at' => $history->created_at,
                    ];
                }

                if ($rows) {
                    DB::table('dtc_audit_logs')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        // Keep audit records and additive columns intact when rolling back.
    }
};
