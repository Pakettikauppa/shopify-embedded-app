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
    Route::get('/auth/refresh', 'AuthController@refreshToken')->name('shopify.auth.refresh');

    Route::get('/preferences', 'AppController@preferences')->name('shopify.preferences');
    Route::get('/print-orders', 'AppController@printOrders')->name('shopify.print-orders');
});

