<?php

namespace App\Http\Middleware;

use App\Lib\AuthRedirection;
use Closure;
use Illuminate\Http\Request;
use Shopify\Utils;
use App\Models\Shopify\Shop;
use App\Models\Shopify\Session;

class EnsureShopifyInstalled
{
    /**
     * Checks if the shop in the query arguments is currently installed.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $shop = $request->query('shop') ? Utils::sanitizeShopDomain($request->query('shop')) : null;

        $token = Session::where('shop', $shop)->where('access_token', '<>', null)->first();
        $tokenValid = false;
        if($token) {
            $tokenValid = $this->tokenIsValid($shop, $token);
        }

        // If token is invalid (invalidated after uninstalling the app), we direct to installation authflow.
        if(!$tokenValid) {
            return AuthRedirection::redirect($request);
        }

        $appInstalled = $shop && Shop::where('shop_origin', $shop)->exists() && Session::where('shop', $shop)->where('access_token', '<>', null)->exists();
        $isExitingIframe = preg_match("/^ExitIframe/i", $request->path());

        return ($appInstalled || $isExitingIframe) ? $next($request) : AuthRedirection::redirect($request);
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
}
