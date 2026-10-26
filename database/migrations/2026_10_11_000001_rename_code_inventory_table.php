<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('unique_codes') && Schema::hasTable('dtc_codes')) {
            throw new \RuntimeException('Both DTC code tables exist. Resolve the table contents before running this migration.');
        }

        if (Schema::hasTable('unique_codes')) {
            Schema::rename('unique_codes', 'dtc_codes');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dtc_codes') && Schema::hasTable('unique_codes')) {
            throw new \RuntimeException('Both DTC code tables exist. Resolve the table contents before rolling back this migration.');
        }

        if (Schema::hasTable('dtc_codes')) {
            Schema::rename('dtc_codes', 'unique_codes');
        }
    }
};
