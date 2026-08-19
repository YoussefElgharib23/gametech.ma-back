<?php

namespace App\Http\Middleware;

use App\Models\Visitor;
use App\Support\VisitorToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateVisitor
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $visitor = $this->visitorFromRequest($request);

        if ($visitor instanceof Visitor) {
            Auth::guard('visitor')->setUser($visitor);
            $request->setUserResolver(fn () => $visitor);
        } elseif ($mode !== 'optional') {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }

    private function visitorFromRequest(Request $request): ?Visitor
    {
        $plainTextToken = VisitorToken::fromRequest($request);

        if ($plainTextToken === null) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);

        if ($accessToken === null || ! $accessToken->tokenable instanceof Visitor) {
            return null;
        }

        if ($accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            return null;
        }

        $accessToken->forceFill([
            'last_used_at' => now(),
        ])->save();

        /** @var Visitor $visitor */
        $visitor = $accessToken->tokenable;

        return $visitor->withAccessToken($accessToken);
    }
}
