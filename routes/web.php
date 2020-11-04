<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return response('Labas');
//     //return redirect('https://www.pakettikauppa.fi');
// });

//Route::get('/', 'Test@index')->name('install-link');

Route::group(['namespace' => 'Shopify'], function () {
    Route::get('/auth', 'AuthController@index')->name('install-link');
    //Route::get('/auth/', 'AuthController@index')->name('shopify.auth.index');
    Route::get('/auth/callback', 'AuthController@callback')->name('shopify.auth.callback');

    Route::group(['middleware' => ['shopify', 'shopify.shop', 'shopify.localize']], function () {
        Route::get('/settings/shipping', 'SettingsController@shipping')->name('shopify.settings.shipping-link');
        Route::get('/settings/sender', 'SettingsController@sender')->name('shopify.settings.sender-link');
        Route::get('/settings/api', 'SettingsController@api')->name('shopify.settings.api-link');
        Route::get('/settings/generic', 'SettingsController@generic')->name('shopify.settings.generic-link');
        Route::get('/settings/pickuppoints', 'SettingsController@pickuppoints')->name('shopify.settings.pickuppoints-link');

        // TODO: needs to be split into separate jobs
        // Print labels and fullfill
        Route::get('/print-labels-fulfill', 'AppController@printLabelsFulfill')->name('shopify.print-labels-fulfill');
        Route::get('/print-labels', 'AppController@printLabels')->name('shopify.print-labels');
        Route::get('/return-label', 'AppController@returnLabel')->name('shopify.return-label');

        Route::get('/get-label/{order_id}', 'AppController@getLabel')->name('shopify.label');
        Route::post('/get-labels', 'AppController@getLabels')->name('shopify.get_labels');
    });

    // Unsued Routes

    Route::get('/latest-news', 'AppController@latestNews')->middleware('shopify')->name('shopify.latest-news');
    Route::get('/settings', 'AppController@index')->middleware('shopify')->name('shopify.settings');
    Route::get('/settings/set-api-credentials', 'AppController@setApiCredentials')->name('shopify.set-api-credentials');
    Route::get('/settings/sign-contract-link', 'AppController@signContractLink')->name('shopify.sign-contract-link');

    Route::get('/setup-wizard', 'AppController@setupWizard')->name('shopify.setup-wizard');

    // Route::get('/print-labels', 'AppController@printLabels')->name('shopify.print-labels');
    // Route::get('/print-labels-fulfill', 'AppController@printLabelsFulfill')->middleware('shopify')->name('shopify.print-labels-fulfill');
    // Route::get('/return-label', 'AppController@returnLabel')->name('shopify.return-label');

    // Route::get('/get-label/{order_id}', 'AppController@getLabel')->name('shopify.label');
    // Route::post('/get-labels', 'AppController@getLabels')->name('shopify.get_labels');

    Route::get('/track-shipment', 'AppController@trackShipment')->name('shopify.track-shipment');
});

