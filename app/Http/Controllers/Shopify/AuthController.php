<?php

namespace App\Http\Controllers\Shopify;

use App\Exceptions\ShopifyDomainException;
use App\Http\Controllers\Controller;
use App\Lib\AuthRedirection;
use Illuminate\Http\Request;
use App\Models\Shopify\Shop;
use Illuminate\Support\Str;
use App\Models\Shopify\ShopifyClient;
use App\Models\Shopify\Session;
use Log;
use Shopify\Context;
use Shopify\Utils;
use Shopify\Webhooks\Registry;
use Shopify\Webhooks\Topics;
use Storage;
use Shopify\Auth\OAuth;
use Shopify\Exception\CookieNotFoundException;

class AuthController extends Controller
{
    private $type;
    private $shopifyClient;

    public function __construct(Request $request)
    {
        $this->type = config('shopify.type');
        $this->shopifyClient = null;
    }
    
    public function index(Request $request)
    {
        if (Context::$IS_EMBEDDED_APP &&  $request->query("embedded", false) === "1") {
            $shop = Shop::where('shop_origin', $request->query("shop", null))->firstOrNew();
            \App::setLocale($shop ? $shop->locale : 'en');
            return view('layouts.app', [
               'shop' => $shop,
               'host' => $request->query('host'),
               'type' => $this->type
            ]);
        } else {
            return redirect(Utils::getEmbeddedAppUrl($request->query("host", null)) . "/" . $request->path());
        }
        //return AuthRedirection::redirect($request);
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
        if ($response === false) {
            throw new \Exception(curl_error($curl), curl_errno($curl));
        }
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
        
        try {
            $session = OAuth::callback(
                $request->cookie(),
                $request->query(),
                ['\App\Lib\CookieHandler', 'saveShopifyCookie'],
            );
        } catch (CookieNotFoundException $e) {
            Session::where('shop', $request->get('shop'))->delete();
            Log::error("Authentication callback exception : " . $e->getMessage());
            return redirect()->route('install-link', ['shop' => $request->get('shop')]);
        }
    
        $host = $request->query('host');

        $shop_domain = Utils::sanitizeShopDomain($request->get('shop'));
        $response = Registry::register('/api/webhooks', Topics::APP_UNINSTALLED, $shop_domain, $session->getAccessToken());
        if ($response->isSuccess()) {
            Log::debug("Registered APP_UNINSTALLED webhook for shop $shop_domain with token " . $session->getAccessToken());
        } else {
            Log::error(
                "Failed to register APP_UNINSTALLED webhook for shop $shop_domain with response body: " .
                    print_r($response->getBody(), true)
            );
        }

        $shop = Shop::where('shop_origin', $request->shop)->first();
        if ($shop && $shop->token && $this->tokenIsValid($request->shop, $shop->token)) {
            //
        } else {
            $session = Session::where('shop', $request->shop)->first();
            $token = $session ? $session->access_token : '';

            $shop = $this->saveShop($shop, $request->shop, $token);
        }
    
        $redirectUrl = Utils::getEmbeddedAppUrl($host);
        /*
        if (Config::get('shopify.billing.required')) {
            list($hasPayment, $confirmationUrl) = EnsureBilling::check($session, Config::get('shopify.billing'));
    
            if (!$hasPayment) {
                $redirectUrl = $confirmationUrl;
            }
        }
        */
        return redirect($redirectUrl);
        /*
        // Set default locale (this is required to get correct localization upon initial app load) - Default to english
        \App::setLocale($shop ? $shop->locale : 'en');
        
        return view('layouts.app', [
            'shop' => $shop,
            'type' => $this->type
        ]);
        */
    }

    private function saveShop($shop, $shop_origin, $token)
    {
        if (!$shop) {
            $shop = new Shop();
            // default values
            $shop->test_mode = $this->type == "pakettikauppa" ? true : false;
            $shop->locale = 'fi';
            $shop->shipping_settings = serialize([]);
            $shop->default_service_code = 2103;
            $shop->country = 'FI';

            $shop->shop_origin = $shop_origin;
            $shop->nonce = Str::random(20);
            $shop->token = $token;

            $shop->save();
            
            try {
                $client = $this->getShopifyClient($shop);
                $shop->setDefaultData($client);
            } catch (\Exception $e){
                Log::debug($e->getMessage());
            }

            return $shop;
        }

        if ($shop->token !== $token) {
            $shop->token = $token;

            $shop->save();
        }

        return $shop;
    }
    
    /**
     * Gives ShopifyClient instance if it is created, creates if not. Can be forced to recreate by using $getNew set as true
     * 
     * @param bool $getNew true to create new ShopifyClient instance
     * 
     * @return \App\Models\Shopify\ShopifyClient
     */
    public function getShopifyClient($shop, $getNew = false) {
        if (!$getNew && $this->shopifyClient) {
            return $this->shopifyClient;
        }

        $this->shopifyClient = new ShopifyClient(
                $shop->shop_origin,
                $shop->token,
                config('shopify.api_key'),
                config('shopify.secret')
        );

        return $this->shopifyClient;
    }

    public function exitIframe(Request $request)
    {
        $shop = Shop::where('shop_origin', $request->input('shop', ''))->first();
        return view('layouts.exit-iframe', [
            'shop' => $shop,
            'domain' => $request->input('shop', ''),
            'redirectUrl' => urldecode($request->input('redirectUri', ''))
        ]);
    }
}
