<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeMoneyInput
{
    private const FIELDS = [
        'credit_limit',
        'estimated_monthly_purchase',
        'estimated_quantity',
        'estimated_value',
        'target_price',
        'offered_price',
        'previous_value',
        'requested_value',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $normalized = [];

        foreach (self::FIELDS as $field) {
            if (! $request->exists($field) || is_array($request->input($field))) continue;

            $value = trim((string) $request->input($field));
            if (preg_match('/^-?\d+\.\d{2}$/', $value)) $value = substr($value, 0, -3);
            $normalized[$field] = $value === '' ? null : preg_replace('/[^0-9-]/', '', $value);
        }

        if ($normalized) $request->merge($normalized);

        return $next($request);
    }
}
