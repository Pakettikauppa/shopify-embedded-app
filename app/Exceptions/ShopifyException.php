<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShopifyException extends Exception
{
    public function report(): void
    {
        //
    }

    public function render(Request $request): Response
    {
        \View::share('shop', request()->get('shop'));
        return response()->view('errors.shopifyApi', ['shop' => request()->get('shop')], 500);
    }
}
