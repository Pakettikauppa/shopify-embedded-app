<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class NewPickupPointSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shopify_shops', function (Blueprint $table) {
            $table->jsonb('settings')->nullable();
        });

        DB::Statement('update shopify_shops set settings=\'{}\' where settings is null;');
        DB::Statement('update shopify_shops set settings=settings || \'{"DB Schenker": { "active": "true", "base_price": "0", "trigger_price": "", "triggered_price": ""}}\' where pickuppoint_providers like \'%DB Schenker%\'');
        DB::Statement('update shopify_shops set settings=settings || \'{"Matkahuolto": { "active": "true", "base_price": "0", "trigger_price": "", "triggered_price": ""}}\' where pickuppoint_providers like \'%Matkahuolto%\'');
        DB::Statement('update shopify_shops set settings=settings || \'{"Posti": { "active": "true", "base_price": "0", "trigger_price": "", "triggered_price": ""}}\' where pickuppoint_providers like \'%Posti%\'');

        Schema::table('shopify_shops', function (Blueprint $table) {
            $table->dropColumn('pickuppoint_providers');
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
            $table->text('pickuppoint_providers')->nullable();
            $table->dropColumn('settings');
        });
    }
}
