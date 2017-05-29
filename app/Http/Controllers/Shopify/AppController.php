<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use Oak\Models\Shopify\Shop;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AppController extends Controller
{
    private $client;
    private $shop;
    public function __construct(Request $request)
    {
        if(isset($request->hmac) && isset($request->shop)){
            // request from shopify
            $this->middleware(function ($request, $next) {
                $shop_origin = $request->shop;
                $shop = Shop::where('shop_origin', $shop_origin)->first();
                if(!isset($shop)){
                    throw new UnprocessableEntityHttpException();
                }
                $this->shop = $shop;
                $this->client = new ShopifyClient($shop->shop_origin, $shop->token, ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

                if(!$this->client->validateSignature($request->all())){
                    throw new UnprocessableEntityHttpException();
                }

                if(!session()->has('shop')){
                    session()->put('shop', $request->shop);
                }
                return $next($request);
            });
        }else{
            // request from the app
            $this->middleware(function ($request, $next) {
                if(!session()->has('shop')){
                    throw new UnprocessableEntityHttpException();
                }

                $shop_origin = session()->get('shop');
                $shop = Shop::where('shop_origin', $shop_origin)->first();
                if(!isset($shop)){
                    throw new UnprocessableEntityHttpException();
                }
                $this->shop = $shop;
                $this->client = new ShopifyClient($shop->shop_origin, $shop->token, ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

                return $next($request);
            });
        }
    }

    public function preferences(Request $request){
        return view('app.preferences');
    }

    public function printOrders(Request $request){
        dd($request->all());
    }
}
