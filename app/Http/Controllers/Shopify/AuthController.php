<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use Oak\Models\Shopify\Shop;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AuthController extends Controller
{
    public function index(Request $request)
    {
        $client = new ShopifyClient($request->shop, '', ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

        if(!$client->validateSignature($request->all())){
            throw new UnprocessableEntityHttpException();
        }

        $found_shop = Shop::where('shop_origin', $request->shop)->first();
        if(isset($found_shop)){
            $shop = $found_shop;
        }else{
            $shop = new Shop();
            // default values
            $shop->test_mode = true;
            $shop->additional_services = serialize([]);
            $shop->shipping_method_code = 2104; // Kotipaketti is default
            $shop->country = 'FI';
        }

        $nonce = str_random(20);
        $shop->shop_origin = $request->shop;
        $shop->nonce = $nonce;
        $shop->token = '';

        $shop->save();

        $callback_url = route('shopify.auth.callback');
        $redirect_url =  $client->getAuthorizeUrl(ENV('SHOPIFY_SCOPE'), $callback_url, $nonce);

        return redirect($redirect_url);
    }

    public function callback(Request $request)
    {
        $client = new ShopifyClient($request->shop, '', ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

        if(!$client->validateSignature($request->all())){
            throw new UnprocessableEntityHttpException();
        }

        $shop = Shop::where('shop_origin', $request->shop)->where('nonce', $request->state)->first();
        if(!isset($shop)){
            throw new UnprocessableEntityHttpException();
        }

        $shop->token = $client->getAccessToken($request->code);
        $shop->save();

        session()->put('shop', $request->shop);

        if(session()->has('init_request')){
            $init_request = session()->get('init_request');
            session()->forget('init_request');
            return redirect($init_request);
        }

        return redirect()->route('shopify.settings');
    }
}
