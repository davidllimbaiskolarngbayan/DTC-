<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('dtc_id');
            $table->string('claim_number')->nullable()->unique();
            $table->string('subject', 120);
            $table->text('description');
            $table->boolean('eligible');
            $table->text('eligibility_reason');
            $table->string('status', 32)->default('submitted')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('dtc_id')->references('id')->on('dtc_users')->cascadeOnDelete();
            $table->index(['dtc_id', 'created_at']);
        });

        Schema::create('claim_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['claim_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_histories');
        Schema::dropIfExists('claims');
    }
};
