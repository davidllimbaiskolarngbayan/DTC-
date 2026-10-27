<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('batch_sends')) {
            Schema::create('batch_sends', function (Blueprint $table) {
                $table->increments('id');
                $table->string('batch');
                $table->boolean('email_sent')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('campaign_recipients')) {
            Schema::create('campaign_recipients', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('campaign_steps_id');
                $table->integer('user_id');
                $table->string('status')->default('pending');
                $table->boolean('is_automated')->default(true);
                $table->timestamps();
                $table->index('campaign_steps_id');
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('campaign_steps')) {
            Schema::create('campaign_steps', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('campaign_id');
                $table->integer('send_after');
                $table->string('name');
                $table->string('description')->nullable();
                $table->text('conditions')->nullable();
                $table->timestamps();
                $table->index('campaign_id');
            });
        }

        if (! Schema::hasTable('campaign_steps_edms')) {
            Schema::create('campaign_steps_edms', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('campaign_steps_id');
                $table->string('subject');
                $table->string('sender_name');
                $table->string('sender_email');
                $table->string('filename');
                $table->string('template_path');
                $table->timestamps();
                $table->unique('campaign_steps_id');
            });
        }

        if (! Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('campaign_date_reference')->default('purchase_date');
                $table->dateTime('start_date')->nullable();
                $table->string('status')->default('inactive');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dtc_stores')) {
            Schema::create('dtc_stores', function (Blueprint $table) {
                $table->increments('id');
                $table->string('store_name');
                $table->text('url')->nullable();
                $table->string('state')->default('');
                $table->string('category')->default('');
                $table->boolean('is_dtc')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dtc_tyres')) {
            Schema::create('dtc_tyres', function (Blueprint $table) {
                $table->increments('id');
                $table->string('tyre_name');
                $table->string('post_excerpt')->default('');
                $table->string('slug')->default('');
                $table->string('url')->default('');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dtc_user_has_errors')) {
            Schema::create('dtc_user_has_errors', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id');
                $table->string('error_message');
                $table->timestamps();
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('import_errors')) {
            Schema::create('import_errors', function (Blueprint $table) {
                $table->increments('id');
                $table->string('firstname')->nullable();
                $table->string('lastname')->nullable();
                $table->string('email')->nullable();
                $table->string('contact_no', 50)->nullable();
                $table->text('vehicle')->nullable();
                $table->string('vehicle_reg_id', 50)->nullable();
                $table->string('tyre')->nullable();
                $table->integer('quantity')->nullable();
                $table->dateTime('date_of_purchase')->nullable();
                $table->string('state')->nullable();
                $table->string('area')->nullable();
                $table->string('store_name')->nullable();
                $table->boolean('is_dtc')->default(false);
                $table->boolean('tnc_agree')->default(false);
                $table->boolean('subscribe')->default(false);
                $table->boolean('online')->default(true);
                $table->string('batch', 20);
                $table->string('error');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sendgrids')) {
            Schema::create('sendgrids', function (Blueprint $table) {
                $table->increments('id');
                $table->string('email');
                $table->string('event_type');
                $table->string('url_clicked')->default('');
                $table->string('category')->default('');
                $table->string('step_id')->default('');
                $table->dateTime('transaction_date');
                $table->string('message_id')->default('');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sendgrids');
        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('dtc_user_has_errors');
        Schema::dropIfExists('dtc_tyres');
        Schema::dropIfExists('dtc_stores');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('campaign_steps_edms');
        Schema::dropIfExists('campaign_steps');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('batch_sends');
    }
};
