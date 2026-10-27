<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dtc_audit_logs')) {
            Schema::create('dtc_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('dtc_id')->nullable()->index();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 100);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('api_security_events')) {
            Schema::create('api_security_events', function (Blueprint $table) {
                $table->id();
                $table->string('event', 100);
                $table->string('ip_address', 45)->nullable()->index();
                $table->string('method', 10)->nullable();
                $table->string('path', 2048)->nullable();
                $table->json('details')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_security_events');
        Schema::dropIfExists('dtc_audit_logs');
    }
};