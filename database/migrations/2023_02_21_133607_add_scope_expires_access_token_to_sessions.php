<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shopify_sessions', function (Blueprint $table) {
            $table->boolean('is_online')->nullable(false)->after('session_data');
            $table->string('state')->nullable(false)->after('session_data');
            $table->string('shop')->nullable()->after('session_data');
            $table->string('scope')->nullable()->after('session_data');
            $table->string('access_token')->nullable()->after('session_data');
            $table->dateTime('expires_at')->nullable()->after('session_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shopify_sessions', function (Blueprint $table) {
            $table->dropColumn('is_online');
            $table->dropColumn('state');
            $table->dropColumn('shop');
            $table->dropColumn('scope');
            $table->dropColumn('access_token');
            $table->dropColumn('expires_at');
        });
    }
};
