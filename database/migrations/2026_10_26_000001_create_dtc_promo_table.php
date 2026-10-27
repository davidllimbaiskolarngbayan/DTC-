<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dtc_promo')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `dtc_promo` ENGINE=InnoDB, MODIFY `promo_key` VARCHAR(50) NOT NULL');
                DB::statement('ALTER TABLE `dtc_promo` ADD UNIQUE KEY `dtc_promo_user_id_promo_key_unique` (`user_id`, `promo_key`)');
                DB::statement('ALTER TABLE `dtc_promo` ADD CONSTRAINT `dtc_promo_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dtc_users` (`id`) ON DELETE CASCADE');

                return;
            }

            if (! Schema::hasIndex('dtc_promo', ['user_id', 'promo_key'], 'unique')) {
                Schema::table('dtc_promo', function (Blueprint $table) {
                    $table->unique(['user_id', 'promo_key']);
                });
            }

            $hasUserForeignKey = collect(Schema::getForeignKeys('dtc_promo'))->contains(
                fn (array $foreignKey) => $foreignKey['columns'] === ['user_id']
                    && $foreignKey['foreign_table'] === 'dtc_users'
                    && $foreignKey['foreign_columns'] === ['id']
            );

            if (! $hasUserForeignKey) {
                Schema::table('dtc_promo', function (Blueprint $table) {
                    $table->foreign('user_id')->references('id')->on('dtc_users')->cascadeOnDelete();
                });
            }

            return;
        }

        Schema::create('dtc_promo', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $table->engine = 'InnoDB';
            }
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('promo_key', 50);
            $table->string('promo_value');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('dtc_users')->cascadeOnDelete();
            $table->unique(['user_id', 'promo_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtc_promo');
    }
};
