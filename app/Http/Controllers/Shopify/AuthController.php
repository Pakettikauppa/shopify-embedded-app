<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use App\Models\Shopify\Shop;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Illuminate\Support\Str;
use Cookie;
//use Log;

class AuthController extends Controller
{
    public function index(Request $request)
    {
        // $client = new ShopifyClient($request->shop, '', ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

        // if (!$client->validateSignature($request->all())) {
        //     throw new UnprocessableEntityHttpException();
        // }

        // $found_shop = Shop::where('shop_origin', $request->shop)->first();
        // if (!empty($found_shop)) {
        //     $shop = $found_shop;
        // } else {
        //     Log::debug("Shop origin = " . $request->shop);

        //     $shop = new Shop();
        //     // default values
        //     $shop->test_mode = true;
        //     $shop->locale = 'fi';
        //     $shop->shipping_settings = serialize([]);
        //     $shop->default_service_code = 2103;
        //     $shop->country = 'FI';
        // }

        // $nonce = Str::random(20);
        // $shop->shop_origin = $request->shop;
        // $shop->nonce = $nonce;
        // $shop->token = '';
        // $shop->save();

        // $callback_url = route('shopify.auth.callback');
        // $redirect_url = $client->getAuthorizeUrl(ENV('SHOPIFY_SCOPE'), $callback_url, $nonce);
        // $params = $request->all();
        // $params['_enable_cookies'] = 'yes';

        // $enable_cookies_url = route('shopify.auth.index', $params);

        // if (session()->has('init_request')) {
        //     // TODO, use JAvascript to do the redirect
        //     Log::debug('redirecting in auth 1');
        //     //            return redirect($redirect_url);
        //     return view('app.redirect', [
        //         'redirect_url' => $redirect_url,
        //         'shop_origin' => $shop->shop_origin,
        //         'enable_cookies_url' => $redirect_url,
        //     ]);
        // }


        // if ($request->get('_pk_s') !== null) {
        //     Log::debug('Setting init_request to ' . $request->get('_pk_s'));

        //     session()->put('init_request', base64_decode($request->get('_pk_s')));
        //     session()->save();
        // }
        // if ($request->get('_enable_cookies') == 'yes') {
        //     return view('app.create-session', [
        //         'shop_origin' => $shop->shop_origin,
        //         'redirect_url' => 'https://' . $shop->shop_origin . '/admin/apps/' . env('SHOPIFY_API_KEY'),
        //     ]);
        // }
        // Log::debug("Go to a redirect page");

        // return view('app.redirect', [
        //     'redirect_url' => $redirect_url,
        //     'shop_origin' => $shop->shop_origin,
        //     'enable_cookies_url' => $enable_cookies_url,
        // ]);
    }

    public function enableCookies(Request $request)
    {
    }

    private function validateHMAC($paramString)
    {
        $params = [];
        parse_str($paramString, $params);
        if (!isset($params['hmac'])) {
            return false;
          }
        $hmac = $params['hmac'];
        unset($params['hmac']);
        ksort($params);
        $computed_hmac = hash_hmac('sha256', http_build_query($params), config('shopify.secret'));

        return hash_equals($computed_hmac, $hmac);
    }

    private function getAccessToken($shop, $apiKey, $secret, $code)
    {
        $query = array(
            'client_id' => $apiKey,
            'client_secret' => $secret,
            'code' => $code
        );

        // Build access token URL
        $access_token_url = "https://$shop/admin/oauth/access_token";

        // Configure curl client and execute request
        $curl = curl_init();
        $curlOptions = array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_URL => $access_token_url,
            CURLOPT_POSTFIELDS => http_build_query($query)
        );
        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);
        $jsonResponse = json_decode($response, TRUE);
        curl_close($curl);

        file_put_contents(storage_path('logs/token.log'), '==== ' . date('Y-m-d H:i:s') . ' ====' . PHP_EOL . $response . PHP_EOL, FILE_APPEND);

        return $jsonResponse['access_token'];
    }

    private function tokenIsValid($shop, $token)
    {

        // Build access token URL
        $url = "https://$shop/admin/oauth/access_scopes.json";

        // Configure curl client and execute request
        $curl = curl_init();
        $curlOptions = array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => ['X-Shopify-Access-Token: ' . $token]
        );
        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);
        $jsonResponse = json_decode($response, TRUE);

        curl_close($curl);

        //dd($response);

        if (isset($jsonResponse['errors'])) {
            return false;
        }

        return true;
    }

    public function callback(Request $request)
    {
        // TODO: VALIDATE HMAC HERE
        if (!$this->validateHMAC($request->getQueryString())) {
            dd('BAD HMAC');
            return redirect()->route('install-link', ['shop' => $request->get('shop')]);
        }
        // $client = new ShopifyClient($request->shop, '', ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

        // if (!$client->validateSignature($request->all())) {
        //     throw new UnprocessableEntityHttpException();
        // }

        // $shop = Shop::where('shop_origin', $request->shop)->where('nonce', $request->state)->first();
        // if (empty($shop)) {
        //     Log::debug("shop not found");
        //     throw new UnprocessableEntityHttpException();
        // }
        $shop = Shop::where('shop_origin', $request->shop)->first();
        if ($shop->token && $this->tokenIsValid($request->shop, $shop->token)) {
            //
        } else {
            $token = $this->getAccessToken($request->shop, config('shopify.api_key'), config('shopify.secret'), $request->code);

            $this->saveShop($shop, $request->shop, $token);
        }

        //dd([$shop, $request]);

        // Log::debug("setting topLevelOAuth cookie to no");
        // session()->put('shopify_version', '1');
        // session()->put('shop', $request->shop);


        // if (session()->has('init_request')) {
        //     Log::debug("Redirecting to ".session()->get('init_request'));
        //     $init_request = session()->get('init_request');
        //     $init_request = str_replace(array('http:'), array('https:'), $init_request);
        //     session()->forget('init_request');

        //     return redirect($init_request);
        // }
        // Log::debug("Redirecting to settings");
        \App::setLocale($shop ? $shop->locale : 'en');
        return view('layouts.app', [
            'shop' => $shop,
        ]);
        // $token = $this->makeJWT($request->shop);
        // return redirect()->route('shopify.settings', ['local_token' => $token]);
    }

    private function saveShop($shop, $shop_origin, $token)
    {
        if (!$shop) {
            $shop = new Shop();
            // default values
            $shop->test_mode = true;
            $shop->locale = 'fi';
            $shop->shipping_settings = serialize([]);
            $shop->default_service_code = 2103;
            $shop->country = 'FI';

            $shop->shop_origin = $shop_origin;
            $shop->nonce = Str::random(20);
            $shop->token = $token;

            return $shop->save();
        }

        if ($shop->token !== $token) {
            $shop->token = $token;

            return $shop->save();
        }

        return true; // nothing changed
    }

    private function makeJWT($shopOrigin)
    {
        $header = [
            'type' => 'JWT'
        ];
        $payload = [
            'dest' => 'https://' . $shopOrigin,
            'exp' => time() + 120,
            'nbf' => time(),
        ];

        $header = $this->base64UrlEncode(json_encode($header));
        $payload = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, config('shopify.secret'), true));

        return "$header.$payload.$signature";
    }

    private function base64UrlEncode($text)
    {
        return str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode($text)
        );
    }

    private function isValidJWT($header, $payload, $signature)
    {
        return $signature === $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, config('shopify.secret'), true));
    }

    private function parseTokenPayload($payload)
    {
        return json_decode(base64_decode($payload), true);
    }

    private function isExpired($payload_data)
    {
        $now = time();

        return $payload_data['exp'] <= $now || $now < $payload_data['nbf'];
    }
}
