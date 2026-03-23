<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Visitor;
use App\Services\VisitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    public function __construct(
        protected VisitorService $visitorService
    ) {
    }

    /**
     * Create/retrieve a visitor from fingerprint, issue Sanctum token, and ensure cart exists.
     */
    public function visit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fingerprint' => ['required', 'array'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:8'],
            'in_europe' => ['nullable', 'boolean'],
        ]);

        /** @var Visitor|null $authedVisitor */
        $authedVisitor = $request->user();

        if ($authedVisitor instanceof Visitor) {
            $visitor = $authedVisitor;
            $token = null;
        } else {
            $visitor = $this->visitorService->getOrCreateFromFingerprint($data['fingerprint']);
            $token = $visitor->createToken('VISITOR TOKEN')->plainTextToken;
        }

        $visitor->fill([
            'language' => $data['language'] ?? $visitor->language ?? 'fr',
            'in_europe' => array_key_exists('in_europe', $data) ? (bool) $data['in_europe'] : (bool) ($visitor->in_europe ?? false),
            'utm_source' => $data['utm_source'] ?? $visitor->utm_source,
            'utm_campaign' => $data['utm_campaign'] ?? $visitor->utm_campaign,
        ]);
        $visitor->save();

        Cart::query()->firstOrCreate(['visitor_id' => $visitor->id]);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $visitor->id,
                'language' => $visitor->language,
                'in_europe' => (bool) $visitor->in_europe,
                'fingerprint' => $visitor->fingerprint,
                'utm_source' => $visitor->utm_source,
                'utm_campaign' => $visitor->utm_campaign,
            ],
            'has_password' => false,
        ]);
    }
}
