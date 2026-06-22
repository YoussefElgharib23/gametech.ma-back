<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Get the CSRF token from the request.
     *
     * Cross-origin SPAs cannot read the XSRF-TOKEN cookie via JavaScript,
     * but the browser still sends it with credentials: "include" requests.
     */
    protected function getTokenFromRequest($request): ?string
    {
        $token = parent::getTokenFromRequest($request);

        if (! $token && $request->cookies->has('XSRF-TOKEN')) {
            $token = urldecode($request->cookie('XSRF-TOKEN'));
        }

        return $token;
    }
}
