<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Shopify\Context;
use App\Helpers\ShopifySessionStorage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        URL::forceScheme('https');
        URL::forceRootUrl(config('app.url'));
        Context::initialize(
            config('shopify.api_key'),
            config('shopify.secret'),
            config('shopify.scope'),
            config('shopify.app_host_name'),
            new ShopifySessionStorage(storage_path('shopify/sessions')),
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
