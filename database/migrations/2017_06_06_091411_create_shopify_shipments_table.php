<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopifyShipmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shopify_shipments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shop_id');
            $table->foreign('shop_id')->references('id')->on('shopify_shops');
            $table->string('order_id');
            $table->string('status', 20);
            $table->string('tracking_code', 200);
            $table->string('reference', 50);
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
        Schema::drop('shopify_shipments');
    }
}
