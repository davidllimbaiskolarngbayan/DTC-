<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Same name as the migration already recorded in the database; only dtc_users is declared here.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dtc_codes')) {
            Schema::create('dtc_codes', function (Blueprint $table) {
                $table->increments('id');
                $table->string('code')->unique();
                $table->boolean('is_use')->default(false)->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('dtc_users')) {
            return;
        }

        Schema::create('dtc_users', function (Blueprint $table) {
            $table->increments('id');
            // PII columns hold ciphertext (encrypted casts); lookups use the *_hash columns.
            $table->text('firstname');
            $table->text('lastname');
            $table->text('email')->nullable();
            $table->string('email_hash')->nullable()->index();
            $table->text('contact_no');
            $table->string('dtc_card_no')->nullable()->unique();
            $table->string('vehicle')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->text('vehicle_reg_id');
            $table->string('vehicle_reg_hash')->nullable()->index();
            $table->string('tyre');
            $table->string('tyre_size')->nullable();
            $table->string('rim_size')->nullable();
            $table->string('quantity');
            $table->dateTime('date_of_purchase')->index();
            $table->string('state')->nullable();
            $table->string('area')->nullable();
            $table->string('store_name');
            $table->string('store_name_other')->nullable();
            $table->string('created_by')->nullable();
            $table->boolean('dtc_store')->default(false);
            $table->boolean('is_dtc')->default(false);
            $table->boolean('tnc_agree')->default(false);
            $table->boolean('subscribe')->default(false);
            $table->boolean('online')->default(true);
            $table->boolean('is_fix')->default(true);
            $table->string('batch')->nullable();
            $table->string('mileage', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unique_codes');
        Schema::dropIfExists('dtc_codes');
        Schema::dropIfExists('dtc_users');
    }
};
