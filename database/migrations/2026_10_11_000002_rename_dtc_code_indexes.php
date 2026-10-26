<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceIndex('unique_codes_code_unique', 'dtc_codes_code_unique', 'code', true);
        $this->replaceIndex('unique_codes_is_use_index', 'dtc_codes_is_use_index', 'is_use', false);
    }

    public function down(): void
    {
        $this->replaceIndex('dtc_codes_code_unique', 'unique_codes_code_unique', 'code', true);
        $this->replaceIndex('dtc_codes_is_use_index', 'unique_codes_is_use_index', 'is_use', false);
    }

    private function replaceIndex(string $from, string $to, string $column, bool $unique): void
    {
        if (! Schema::hasTable('dtc_codes') || ! Schema::hasIndex('dtc_codes', $from) || Schema::hasIndex('dtc_codes', $to)) {
            return;
        }

        Schema::table('dtc_codes', function (Blueprint $table) use ($from, $to, $column, $unique) {
            if ($unique) {
                $table->dropUnique($from);
                $table->unique($column, $to);
            } else {
                $table->dropIndex($from);
                $table->index($column, $to);
            }
        });
    }
};
