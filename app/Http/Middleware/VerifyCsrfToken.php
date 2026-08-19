<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * Storefront and dashboard APIs authenticate with Sanctum bearer tokens.
     * Stateful CSRF remains enabled for /api/auth/* (see inExceptArray()).
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
    ];

    /**
     * Keep CSRF on dashboard login/logout; skip it for token-based API routes.
     */
    protected function inExceptArray($request): bool
    {
        if ($this->isStatefulAuthRequest($request)) {
            return false;
        }

        return parent::inExceptArray($request);
    }

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
            $token = urldecode((string) $request->cookie('XSRF-TOKEN'));
        }

        return $token;
    }

    private function isStatefulAuthRequest(Request $request): bool
    {
        return $request->is('api/auth', 'api/auth/*');
    }
}
