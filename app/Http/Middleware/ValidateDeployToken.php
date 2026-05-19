<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateDeployToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('elyndor.deploy_token');
        if ($expected === '') {
            return response()->json(['message' => 'Deploy não configurado no servidor.'], 503);
        }

        $provided = $request->bearerToken();
        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        return $next($request);
    }
}
