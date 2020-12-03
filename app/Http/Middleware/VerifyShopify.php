<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\CssSelector\Node\PseudoNode;

class VerifyShopify
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        $hmac = $request->get('hmac');

        // TODO: do not forget to remove auth skipping
        // if ($request->get('virtual')) {
        //     $request->attributes->add(['shopOrigin' => 'posti-dev.myshopify.com']);

        //     return $next($request);
        // }

        if (!$token && !$hmac) {
            dd('Should have a token you know');
        }

        if ($hmac && !isHMACValid($request->getQueryString())) {
            return redirect()->route('install-link', ['shop' => $request->get('shop')]);
        }

        if ($hmac && isHMACValid($request->getQueryString())) {
            $request->attributes->add(['shopOrigin' => $request->get('shop')]);

            return $next($request);
        }

        // Priority is actual Bearer token
        $token_parts = explode('.', $token);
        
        if (count($token_parts) !== 3) {
            dd('Arent you trying to cheat here?');
        }

        if (!$this->isValidJWT($token_parts[0], $token_parts[1], $token_parts[2])) {
            dd('Not valid');
        }

        $payload_data = $this->parseTokenPayload($token_parts[1]);

        if ($this->isExpired($payload_data)) {
            dd('Expired token');
        }

        $request->attributes->add(['shopOrigin' => parse_url($payload_data['dest'], PHP_URL_HOST)]);

        return $next($request);
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
