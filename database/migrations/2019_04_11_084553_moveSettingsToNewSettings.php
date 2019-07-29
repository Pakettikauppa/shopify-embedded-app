<?php

use App\Models\Shopify\Shop;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MoveSettingsToNewSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $shops = Shop::get();

        foreach ($shops as $shop) {
            $old = unserialize($shop->shipping_method);


        }
//        DB::Statement('update shopify_shops set settings=\'{}\' where settings is null;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
