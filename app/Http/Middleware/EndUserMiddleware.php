<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Middleware para usuarios finales (RF-10, RF-11, RF-12)
 * Patrón: Chain of Responsibility - Filtro en cadena de responsabilidad
 * SOLID: Single Responsibility - Solo verifica tipo de usuario final
 */
class EndUserMiddleware
{
    /**
     * Manejar solicitud entrante
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        if (!$user->isEndUser()) {
            return response()->json([
                'message' => 'Acceso denegado. Solo usuarios finales.'
            ], 403);
        }

        return $next($request);
    }
} 