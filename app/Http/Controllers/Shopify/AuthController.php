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
        // TODO: this is where code from Test class index function will go in order to preserve current app settings in partners.shopify.com
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

        file_put_contents(
            storage_path('logs/token.log'),
            '==== ' . date('Y-m-d H:i:s') . ' Request token response ====' . PHP_EOL
                . 'Shop Origin: ' . $shop . PHP_EOL
                . $response . PHP_EOL,
            FILE_APPEND
        );

        return $jsonResponse['access_token'];
    }

    /**
     * Validates current token by requesting access scopes.
     * 
     * @param App\Models\Shopify\Shop $shop Shop object
     * @param string $token token to be validated
     * 
     * @return bool return true if token is valid, false otherwise (request returned errors)
     */
    private function tokenIsValid($shop, $token)
    {

        // Build access token URL
        $url = "https://$shop/admin/oauth/access_scopes.json";

        // Configure curl client and execute request
        $curl = curl_init();
        $curlOptions = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => ['X-Shopify-Access-Token: ' . $token]
        );
        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);
        $jsonResponse = json_decode($response, true);

        curl_close($curl);

        if (isset($jsonResponse['errors'])) {
            return false;
        }

        return true;
    }

    public function callback(Request $request)
    {
        if (!$this->validateHMAC($request->getQueryString())) {
            // In case HMAC is invalid redirect to installation
            return redirect()->route('install-link', ['shop' => $request->get('shop')]);
        }

        // Since HMAC is validated we can assume to have valid information in the URL
        $shop = Shop::where('shop_origin', $request->shop)->first();
        if ($shop->token && $this->tokenIsValid($request->shop, $shop->token)) {
            //
        } else {
            $token = $this->getAccessToken($request->shop, config('shopify.api_key'), config('shopify.secret'), $request->code);

            $this->saveShop($shop, $request->shop, $token);
        }

        // Set default locale (this is required to get correct localization upon initial app load) - Default to english
        \App::setLocale($shop ? $shop->locale : 'en');

        return view('layouts.app', [
            'shop' => $shop,
        ]);
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
