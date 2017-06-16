<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopifyShopsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shopify_shops', function (Blueprint $table) {
            $table->increments('id');
            $table->string('shop_origin');
            $table->string('nonce', 20);
            $table->string('token');
            $table->string('api_key', 80)->nullable();
            $table->string('api_secret', 80)->nullable();
            $table->integer('shipping_method_code');
            $table->text('additional_services');
            $table->boolean('test_mode');
            $table->integer('customer_id');
            $table->string('business_name');
            $table->string('address');
            $table->string('postcode');
            $table->string('city');
            $table->string('country');
            $table->string('email');
            $table->string('phone');
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
        Schema::drop('shopify_shops');
    }
}
