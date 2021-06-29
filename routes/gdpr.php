<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['namespace' => 'Shopify'], function () {
    Route::post('/gdpr/request', 'GDPRController@dataRequest')->name('shopify.gdpr.request');
    Route::post('/gdpr/customer-erase', 'GDPRController@customersRedact')->name('shopify.gdpr.customer');
    Route::post('/gdpr/shop-erasure', 'GDPRController@shopRedact')->name('shopify.gdpr.shop');
});
