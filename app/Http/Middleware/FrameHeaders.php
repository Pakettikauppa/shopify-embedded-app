<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FrameHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $shop_domain = $request->get('shop');
        $response = $next($request);
        $response->header('Content-Security-Policy', "frame-ancestors {$shop_domain} admin.shopify.com");
        return $response;
    }
}
