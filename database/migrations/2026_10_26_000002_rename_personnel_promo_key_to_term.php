<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renamePromoKey('personnel', 'term');
    }

    public function down(): void
    {
        $this->renamePromoKey('term', 'personnel');
    }

    private function renamePromoKey(string $from, string $to): void
    {
        DB::table('dtc_promo')
            ->select('user_id')
            ->where('promo_key', $to)
            ->distinct()
            ->orderBy('user_id')
            ->chunk(500, function ($records) use ($from) {
                DB::table('dtc_promo')
                    ->where('promo_key', $from)
                    ->whereIn('user_id', $records->pluck('user_id'))
                    ->delete();
            });

        DB::table('dtc_promo')->where('promo_key', $from)->update(['promo_key' => $to]);
    }
};
