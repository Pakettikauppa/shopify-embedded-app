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
            $table->boolean('test_mode');
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
