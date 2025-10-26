<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware de API key opcional:
 * - Si API_KEY no está configurada, no aplica (pasa).
 * - Si está configurada, exige header X-API-Key con el mismo valor.
 */
class ApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $need = env('API_KEY');
        if (!$need) {
            return $next($request);
        }
        $got = $request->header('X-API-Key');
        if ($got !== $need) {
            return response()->json(['error' => 'unauthorized'], 401);
        }
        return $next($request);
    }
}