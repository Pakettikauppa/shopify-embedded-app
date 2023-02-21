<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Shopify\Context;
use App\Helpers\ShopifySessionStorage;
use App\Lib\DbSessionStorage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $host = str_replace('https://', '', config('shopify.app_host_name','not_defined'));
        URL::forceScheme('https');
        URL::forceRootUrl(config('app.url'));
        Context::initialize(
            config('shopify.api_key'),
            config('shopify.secret'),
            config('shopify.scope'),
            $host,
            new DbSessionStorage(),
            '2023-01',
            true,
            false,
        );
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
