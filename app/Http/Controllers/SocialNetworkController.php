<?php

namespace App\Http\Controllers;

use App\Services\SocialNetworkService;
use App\Models\BusinessProfile;
use App\Models\SocialNetwork;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador de redes sociales (RF-9)
 * Patrón: Controller Pattern - Maneja requests HTTP para redes sociales
 * SOLID: Single Responsibility - Solo maneja redes sociales HTTP
 * SOLID: Dependency Injection - Inyecta SocialNetworkService
 */
class SocialNetworkController extends Controller
{
    private SocialNetworkService $socialNetworkService;

    public function __construct(SocialNetworkService $socialNetworkService)
    {
        $this->socialNetworkService = $socialNetworkService;
        $this->middleware('auth:api');
    }

    /**
     * Agregar red social a perfil (RF-9)
     */
    public function attachSocialNetwork(Request $request, BusinessProfile $profile): JsonResponse
    {
        // Verificar que el usuario es propietario del perfil
        if ($request->user()->id !== $profile->user_id) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'platform' => 'required|string|in:facebook,instagram,twitter,linkedin,youtube,tiktok,whatsapp',
            'url' => 'required|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $socialNetwork = $this->socialNetworkService->attachSocialNetwork(
                $profile,
                $request->platform,
                $request->url
            );

            return response()->json([
                'message' => 'Red social agregada exitosamente',
                'social_network' => $socialNetwork,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar red social',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Validar URL de red social
     */
    public function validateUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|string|in:facebook,instagram,twitter,linkedin,youtube,tiktok,whatsapp',
            'url' => 'required|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $isValid = $this->socialNetworkService->validatePlatformUrl(
                $request->platform,
                $request->url
            );

            return response()->json([
                'is_valid' => $isValid,
                'message' => $isValid ? 'URL válida' : 'URL inválida'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'is_valid' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Verificar URLs de un perfil (RF-9)
     */
    public function verifyUrls(Request $request, BusinessProfile $profile): JsonResponse
    {
        // Verificar que el usuario es propietario del perfil
        if ($request->user()->id !== $profile->user_id) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        try {
            $results = $this->socialNetworkService->verifyUrls($profile);

            return response()->json([
                'message' => 'Verificación completada',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error en la verificación',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Eliminar red social
     */
    public function removeSocialNetwork(
        Request $request, 
        BusinessProfile $profile, 
        string $platform
    ): JsonResponse {
        // Verificar que el usuario es propietario del perfil
        if ($request->user()->id !== $profile->user_id) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        try {
            $success = $this->socialNetworkService->removeSocialNetwork($profile, $platform);

            if ($success) {
                return response()->json([
                    'message' => 'Red social eliminada'
                ]);
            }

            return response()->json([
                'message' => 'Red social no encontrada'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Activar/desactivar red social
     */
    public function toggleSocialNetwork(Request $request, SocialNetwork $socialNetwork): JsonResponse
    {
        // Verificar que el usuario es propietario del perfil
        if ($request->user()->id !== $socialNetwork->businessProfile->user_id) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        try {
            $updated = $this->socialNetworkService->toggleSocialNetwork($socialNetwork);

            return response()->json([
                'message' => 'Estado actualizado',
                'social_network' => $updated,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar estado',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obtener plataformas soportadas
     */
    public function getSupportedPlatforms(): JsonResponse
    {
        $platforms = $this->socialNetworkService->getSupportedPlatforms();

        return response()->json([
            'platforms' => $platforms
        ]);
    }

    /**
     * Obtener redes sociales de un perfil
     */
    public function getProfileSocialNetworks(BusinessProfile $profile): JsonResponse
    {
        $socialNetworks = $profile->socialNetworks()
            ->active()
            ->get();

        return response()->json([
            'social_networks' => $socialNetworks
        ]);
    }
} 