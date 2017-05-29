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

        $shop = Shop::where('shop_origin', $request->shop)->first() ?: new Shop();

        $nonce = str_random(20);
        $shop->shop_origin = $request->shop;
        $shop->nonce = $nonce;
        $shop->token = '';
        $shop->save();

        $callback_url = route('shopify.auth.callback');
        $redirect_url =  $client->getAuthorizeUrl(ENV('SHOPIFY_SCOPE'), $callback_url, $nonce);

        return redirect($redirect_url);
    }

    public function refreshToken(Request $request){
        $shop_origin = $request->session()->get('shop');

        $shop = Shop::where('shop_origin', $shop_origin)->first();
        if(!isset($shop)) {
            throw new UnprocessableEntityHttpException();
        }

        $nonce = str_random(20);
        $shop->nonce = $nonce;
        $shop->token = '';
        $shop->save();

        $client = new ShopifyClient($shop->shop_origin, '', ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

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

        return redirect()->route('shopify.preferences');
    }
}
