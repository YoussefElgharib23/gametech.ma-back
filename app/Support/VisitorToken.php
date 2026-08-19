<?php

namespace App\Support;

use Illuminate\Http\Request;

final class VisitorToken
{
    public const HEADER = 'X-Visitor-Token';

    /**
     * Resolve the visitor Sanctum token from the X-Visitor-Token header.
     */
    public static function fromRequest(Request $request): ?string
    {
        $header = $request->headers->get(self::HEADER);

        if (! is_string($header) || $header === '') {
            return null;
        }

        return self::plainText($header);
    }

    public static function plainText(string $value): string
    {
        $value = trim($value);

        if (str_starts_with(strtolower($value), 'bearer ')) {
            return trim(substr($value, 7));
        }

        return $value;
    }
}
