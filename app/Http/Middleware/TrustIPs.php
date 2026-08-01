<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

final class TrustIPs
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$trustedIpRanges): Response
    {
        $clientIp = $request->ip();

        if (
            ! is_string($clientIp)
            || $trustedIpRanges === []
            || ! IpUtils::checkIp($clientIp, $trustedIpRanges)
        ) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
