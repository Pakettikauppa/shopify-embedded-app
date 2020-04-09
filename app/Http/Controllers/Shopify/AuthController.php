<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use App\Models\Shopify\Shop;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Cookie;
use Log;

class AuthController extends Controller
{
    public function index(Request $request)
    {
        $client = new ShopifyClient($request->shop, '', ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

        if (!$client->validateSignature($request->all())) {
            throw new UnprocessableEntityHttpException();
        }

        $found_shop = Shop::where('shop_origin', $request->shop)->first();
        if (!empty($found_shop)) {
            $shop = $found_shop;
        } else {
            Log::debug("Shop origin = ". $request->shop);

            $shop = new Shop();
            // default values
            $shop->test_mode = true;
            $shop->locale = 'fi';
            $shop->shipping_settings = serialize([]);
            $shop->default_service_code = 2103;
            $shop->country = 'FI';
        }

        $nonce = str_random(20);
        $shop->shop_origin = $request->shop;
        $shop->nonce = $nonce;
        $shop->token = '';
        $shop->save();

        $callback_url = route('shopify.auth.callback');
        $redirect_url = $client->getAuthorizeUrl(ENV('SHOPIFY_SCOPE'), $callback_url, $nonce);
        $params = $request->all();
        $params['_enable_cookies'] = 'yes';

        $enable_cookies_url = route('shopify.auth.index', $params);

        if (session()->has('init_request')) {
            // TODO, use JAvascript to do the redirect
            Log::debug('redirecting in auth 1');
//            return redirect($redirect_url);
            return view('app.redirect', [
                'redirect_url' => $redirect_url,
                'shop_origin' => $shop->shop_origin,
                'enable_cookies_url' => $redirect_url,
            ]);
        }


        if ($request->get('_pk_s') !== null) {
            Log::debug('Setting init_request to '. $request->get('_pk_s'));

            session()->put('init_request', base64_decode($request->get('_pk_s')));
            session()->save();
        }
        if ($request->get('_enable_cookies') == 'yes') {
            return view('app.create-session', [
                'shop_origin' => $shop->shop_origin,
                'redirect_url' => 'https://'.$shop->shop_origin.'/admin/apps/'.env('SHOPIFY_API_KEY'),
            ]);
        }
        Log::debug("Go to a redirect page");

        return view('app.redirect', [
            'redirect_url' => $redirect_url,
            'shop_origin' => $shop->shop_origin,
            'enable_cookies_url' => $enable_cookies_url,
        ]);
    }

    public function enableCookies(Request $request)
    {

    }

    public function callback(Request $request)
    {
        $client = new ShopifyClient($request->shop, '', ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

        if (!$client->validateSignature($request->all())) {
            throw new UnprocessableEntityHttpException();
        }

        $shop = Shop::where('shop_origin', $request->shop)->where('nonce', $request->state)->first();
        if (empty($shop)) {
            Log::debug("shop not found");
            throw new UnprocessableEntityHttpException();
        }

        $shop->token = $client->getAccessToken($request->code);
        $shop->save();

        Log::debug("setting topLevelOAuth cookie to no");
        session()->put('shopify_version', '1');
        session()->put('shop', $request->shop);


        if (session()->has('init_request')) {
            Log::debug("Redirecting to ".session()->get('init_request'));
            $init_request = session()->get('init_request');
            $init_request = str_replace(array('http:'), array('https:'), $init_request);
            session()->forget('init_request');

            return redirect($init_request);
        }
        Log::debug("Redirecting to settings");
        return redirect()->route('shopify.settings');
    }
}
