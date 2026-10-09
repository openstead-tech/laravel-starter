<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAcceptanceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('acceptance.token');
        $provided = (string) $request->header('X-Runivo-Acceptance', '');
        if (strlen($expected) < 32 || !hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
