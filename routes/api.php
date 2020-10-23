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
    Route::post('/pickup-points', 'PickupPointsController@list')->name('shopify.pickuppoints.list');

    Route::group(['middleware' => ['shopify', 'shopify.shop', 'shopify.localize']], function () {
        Route::get('/buttons-translation', 'SettingsController@getButtonsTranslations')->name('shopify.button-translations');

        Route::post('/settings/update/testmode', 'SettingsController@updateTestMode')->name('shopify.update-test-mode');
        Route::post('/settings/update/api', 'SettingsController@updateApiSettings')->name('shopify.update-api');
        Route::post('/settings/update/shipping', 'SettingsController@updateShippingSettings')->name('shopify.update-shipping');
        Route::post('/settings/update/locale', 'SettingsController@updateLocale')->name('shopify.update-locale');
        Route::post('/settings/update/sender', 'SettingsController@updateSender')->name('shopify.update-sender');
        Route::post('/settings/update/pickuppoints', 'SettingsController@updatePickupPoints')->name('shopify.update-pickuppoints');

        // old save method - to be refactored and removed
        Route::post('/settings/update', 'SettingsController@updateSettings')->middleware('shopify')->name('shopify.update-settings');
    });
});
