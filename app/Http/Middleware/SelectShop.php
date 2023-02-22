<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Shopify\Shop;

class SelectShop
{
    /**
     * Adds Shop to request by shopOrigin set with VerifyShopify middleware
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $request->attributes->add(['shop' => Shop::where('shop_origin', $request->get('shop'))->first()]);
        return $next($request);
    }
}
