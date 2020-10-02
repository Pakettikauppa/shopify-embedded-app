<?php

namespace App\Http\Middleware;

use Closure;

class Localize
{
    /**
     * Uses Shop data from SelectShop middleware to set localization
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $shop = $request->get('shop');

        \App::setLocale($shop ? $shop->locale : 'en');

        return $next($request);
    }
}
