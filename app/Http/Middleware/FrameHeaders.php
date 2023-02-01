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
        $response->header('Content-Security-Policy', "frame-ancestors https://{$shop_domain} https://admin.shopify.com;");
//        $response->header('Content-Security-Policy', "default-src 'self'; script-src 'self' cdn.shopify.com cdn.shopifycloud.com; style-src 'self' cdn.shopify.com cdn.shopifycloud.com 'unsafe-inline'; img-src 'self' cdn.shopify.com cdn.shopifycloud.com v.shopify.com data:; font-src 'self' cdn.shopify.com cdn.shopifycloud.com data:; frame-ancestors https://{$shop_domain} https://admin.shopify.com; upgrade-insecure-requests");
        return $response;
    }
}
