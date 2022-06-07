<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppManagerTables extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('marketing_banners', function (Blueprint $table) {
			$table->id();
			$table->text('marketing_banners')->nullable();
			$table->timestamps();
		});

        Schema::create('plans', function (Blueprint $table) {
			$table->id();
			$table->string('type');
			$table->string('name');
			$table->double('price', 8, 2);
			$table->string('offer_text')->nullable();
			$table->text('interval');
			$table->text('shopify_plans')->nullable();
			$table->integer('trial_days')->default(0);
			$table->integer('test')->nullable();
			$table->dateTime('on_install')->nullable();
			$table->boolean('is_custom')->default(false);
			$table->unsignedBigInteger('base_plan')->nullable();
			$table->boolean('public')->default(true);
			$table->integer('discount')->nullable();
			$table->smallInteger('cycle_count')->nullable();
			$table->enum('discount_type', ['amount', 'percentage'])->nullable();
			$table->boolean('store_base_plan')->nullable();
			$table->text('affiliate')->nullable();
			$table->text('feature_plan')->nullable();
			$table->dateTime('deleted_at')->nullable();
			$table->timestamps();
		});

        Schema::create('charges', function (Blueprint $table) {
			$table->id();
			$table->bigInteger('charge_id');
			$table->boolean('test');
			$table->string('status')->nullable();
			$table->string('name')->nullable();
			$table->string('type');
			$table->double('price', 8, 2);
			$table->enum('interval', ['EVERY_30_DAYS', 'ANNUAL']);
			$table->integer('trial_days')->nullable();
			$table->dateTime('billing_on')->nullable();
			$table->dateTime('activated_on')->nullable();
			$table->dateTime('trial_ends_on')->nullable();
			$table->dateTime('cancelled_on')->nullable();
			$table->dateTime('expires_on')->nullable();
			$table->unsignedBigInteger('plan_id');
			$table->text('description')->nullable();
			$table->string('shop_domain');
			$table->boolean('sync')->default(true);
            $table->string('process_type')->nullable();
			$table->timestamps();
		});

        Schema::create('discount_plan', function (Blueprint $table) {
			$table->id();
			$table->smallInteger('discount');
			$table->enum('discount_type', ['amount', 'percentage']);
			$table->smallInteger('cycle_count')->nullable();
			$table->unsignedBigInteger('created_by');
			$table->string('shop_domain');
			$table->unsignedBigInteger('plan_id')->nullable();
			$table->timestamps();
		});

        Schema::create('trial_extension', function (Blueprint $table) {
			$table->id();
			$table->integer('days');
			$table->unsignedBigInteger('plan_id');
			$table->string('shop_domain');
			$table->unsignedBigInteger('created_by');
			$table->dateTime('extend_trial_start_at')->nullable();
			$table->timestamps();
		});

        Schema::create('plan_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('created_by');
            $table->string('shop_domain');
            $table->timestamps();
        });
    }

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('marketing_banners');
		Schema::dropIfExists('plans');
		Schema::dropIfExists('charges');
		Schema::dropIfExists('discount_plan');
		Schema::dropIfExists('trial_extension');
		Schema::dropIfExists('plan_user');
	}
}
