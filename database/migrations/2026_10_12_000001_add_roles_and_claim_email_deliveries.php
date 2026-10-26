<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 32)->default('staff')->index();
            });
        }

        if (! Schema::hasTable('claim_email_deliveries')) {
            Schema::create('claim_email_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
                $table->foreignId('claim_history_id')->nullable()->constrained('claim_histories')->nullOnDelete();
                $table->string('type', 32);
                $table->string('recipient_hash', 64)->nullable();
                $table->string('status', 32)->default('pending')->index();
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['claim_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_email_deliveries');
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
        }
    }
};