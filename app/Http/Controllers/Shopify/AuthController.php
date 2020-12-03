<?php

namespace App\Http\Controllers\Shopify;

use App\Exceptions\ShopifyDomainException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shopify\Shop;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function index(Request $request)
    {
        // Check that shopdomain is valid
        $shop_domain = $request->input('shop', '');
        if (!$this->isValidShopDomain($shop_domain)) {
            throw new ShopifyDomainException('Shop domain is not valid. Must be like - shopname.myshopify.com');
        }

        $api_key = config('shopify.api_key');
        $scopes = config('shopify.scope');
        $redirect_uri = route('shopify.auth.callback');

        // Build install/approval URL to redirect to
        $install_url = "https://" . $shop_domain . "/admin/oauth/authorize?client_id=" . $api_key . "&scope=" . $scopes . "&redirect_uri=" . urlencode($redirect_uri);

        // Due to how shopify works redirection must be done on shopify end (as app is loaded inside iframe)
        return view('app.entry', [
            'shopOrigin' => $shop_domain,
            'api_key' => $api_key,
            'install_url' => $install_url
        ]);
    }

    private function isValidShopDomain($shop)
    {
        $substring = explode('.', $shop);

        // 'domain.myshopify.com'
        if (count($substring) != 3) {
            return false;
        }

        // allow dashes and alphanumberic characters
        $substring[0] = str_replace('-', '', $substring[0]);
        return (ctype_alnum($substring[0]) && $substring[1] . '.' . $substring[2] == 'myshopify.com');
    }

    public function enableCookies(Request $request)
    {
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

        // Uncomment for debuging received tokens
        // file_put_contents(
        //     storage_path('logs/token.log'),
        //     '==== ' . date('Y-m-d H:i:s') . ' Request token response ====' . PHP_EOL
        //         . 'Shop Origin: ' . $shop . PHP_EOL
        //         . $response . PHP_EOL,
        //     FILE_APPEND
        // );

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
        if (!isHMACValid($request->getQueryString())) {
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
}
