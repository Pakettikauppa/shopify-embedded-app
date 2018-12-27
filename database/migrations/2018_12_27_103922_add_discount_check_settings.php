<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDiscountCheckSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shopify_shops', function (Blueprint $table) {
            $table->boolean('include_discounted_price_in_trigger')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shopify_shops', function (Blueprint $table) {
            $table->dropColumn('include_discounted_price_in_trigger');
        });
    }
}
