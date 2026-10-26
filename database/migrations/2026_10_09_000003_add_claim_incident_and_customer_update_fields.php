<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->date('incident_date')->nullable()->after('description');
            $table->string('affected_tyres', 160)->nullable()->after('incident_date');
        });

        Schema::table('claim_histories', function (Blueprint $table) {
            $table->text('customer_message')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('claim_histories', function (Blueprint $table) {
            $table->dropColumn('customer_message');
        });

        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn(['incident_date', 'affected_tyres']);
        });
    }
};
