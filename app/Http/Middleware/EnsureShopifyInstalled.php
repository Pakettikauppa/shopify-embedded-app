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

        $appInstalled = $shop && Shop::where('shop_origin', $shop)->exists() && Session::where('shop', $shop)->where('access_token', '<>', null)->exists();
        $isExitingIframe = preg_match("/^ExitIframe/i", $request->path());

        return ($appInstalled || $isExitingIframe) ? $next($request) : AuthRedirection::redirect($request);
    }
}
