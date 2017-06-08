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


Route::group(['namespace' => 'Shopify'], function () {
    Route::get('/auth/', 'AuthController@index')->name('shopify.auth.index');
    Route::get('/auth/callback', 'AuthController@callback')->name('shopify.auth.callback');

    Route::get('/settings', 'AppController@settings')->name('shopify.settings');
    Route::get('/settings/update', 'AppController@updateSettings')->name('shopify.update-settings');

    Route::get('/print-labels', 'AppController@printLabels')->name('shopify.print-labels');

    Route::get('/get-label/{order_id}', 'AppController@getLabel')->name('shopify.label');
    Route::get('/track-shipment', 'AppController@trackShipment')->name('shopify.track-shipment');
});

