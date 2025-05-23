<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Middleware para usuarios de tipo business (RF-3, RF-4)
 * Patrón: Chain of Responsibility - Filtro en cadena de responsabilidad
 * SOLID: Single Responsibility - Solo verifica tipo de usuario business
 */
class BusinessUserMiddleware
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

        if (!$user->isBusinessUser()) {
            return response()->json([
                'message' => 'Acceso denegado. Solo usuarios de tipo business.'
            ], 403);
        }

        return $next($request);
    }
} 