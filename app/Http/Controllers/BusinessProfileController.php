<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Services\BusinessProfileService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Controlador para perfiles de negocio
 * SOLID: Single Responsibility - Solo maneja HTTP requests para perfiles
 * SOLID: Dependency Inversion - Depende de servicios, no implementaciones concretas
 * Patrón: Controller Pattern - Coordina entre modelos y vistas
 */
class BusinessProfileController extends Controller
{
    private BusinessProfileService $profileService;
    private NotificationService $notificationService;

    /**
     * Inyección de dependencias (SOLID: Dependency Inversion)
     */
    public function __construct(
        BusinessProfileService $profileService,
        NotificationService $notificationService
    ) {
        $this->profileService = $profileService;
        $this->notificationService = $notificationService;
        
        // Middlewares para autenticación y autorización
        $this->middleware('auth:api');
        $this->middleware('business.user')->only(['store', 'update', 'destroy']);
    }

    /**
     * Listar perfiles con filtros (RF-5)
     * GET /api/profiles
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|integer|between:1,50',
            'per_page' => 'nullable|integer|between:6,50',
        ]);

        $profiles = $this->profileService->searchProfiles($filters);

        return response()->json([
            'success' => true,
            'data' => $profiles->items(),
            'pagination' => [
                'current_page' => $profiles->currentPage(),
                'total_pages' => $profiles->lastPage(),
                'total_items' => $profiles->total(),
                'per_page' => $profiles->perPage(),
            ]
        ]);
    }

    /**
     * Crear nuevo perfil de negocio (RF-3)
     * POST /api/profiles
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:categories,id',
        ]);

        try {
            $profile = $this->profileService->createProfile(Auth::user(), $data);
            
            // Notificar creación de perfil
            $this->notificationService->notifyProfileUpdate($profile, 'created');

            return response()->json([
                'success' => true,
                'message' => 'Perfil creado exitosamente',
                'data' => $profile->load(['categories', 'socialNetworks'])
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Mostrar perfil específico (RF-8)
     * GET /api/profiles/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $profile = $this->profileService->getProfileBySlug($slug);

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado'
            ], 404);
        }

        // Obtener estadísticas del perfil
        $stats = $this->profileService->getProfileStats($profile);

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $profile,
                'stats' => $stats
            ]
        ]);
    }

    /**
     * Actualizar perfil de negocio (RF-3)
     * PUT /api/profiles/{profile}
     */
    public function update(Request $request, BusinessProfile $profile): JsonResponse
    {
        // Verificar autorización
        if ($profile->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado para modificar este perfil'
            ], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:categories,id',
        ]);

        $updatedProfile = $this->profileService->updateProfile($profile, $data);
        
        // Notificar actualización
        $this->notificationService->notifyProfileUpdate($updatedProfile, 'updated');

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado exitosamente',
            'data' => $updatedProfile
        ]);
    }

    /**
     * Subir logo del perfil (RF-4)
     * POST /api/profiles/{profile}/logo
     */
    public function uploadLogo(Request $request, BusinessProfile $profile): JsonResponse
    {
        // Verificar autorización
        if ($profile->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado'
            ], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $path = $this->profileService->uploadLogo($profile, $request->file('logo'));

        return response()->json([
            'success' => true,
            'message' => 'Logo subido exitosamente',
            'data' => [
                'logo_path' => $path,
                'logo_url' => $profile->fresh()->logo_url
            ]
        ]);
    }

    /**
     * Eliminar perfil de negocio (RF-3)
     * DELETE /api/profiles/{profile}
     */
    public function destroy(BusinessProfile $profile): JsonResponse
    {
        // Verificar autorización
        if ($profile->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado'
            ], 403);
        }

        // Notificar antes de eliminar
        $this->notificationService->notifyProfileUpdate($profile, 'deleted');
        
        $this->profileService->deleteProfile($profile);

        return response()->json([
            'success' => true,
            'message' => 'Perfil eliminado exitosamente'
        ]);
    }

    /**
     * Obtener perfiles cercanos para mapa (RF-7)
     * GET /api/profiles/nearby
     */
    public function nearby(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|between:1,50',
        ]);

        $profiles = $this->profileService->getNearbyProfiles(
            $data['latitude'],
            $data['longitude'],
            $data['radius'] ?? 10
        );

        return response()->json([
            'success' => true,
            'data' => $profiles
        ]);
    }

    /**
     * Cambiar estado del perfil
     * PATCH /api/profiles/{profile}/toggle-status
     */
    public function toggleStatus(BusinessProfile $profile): JsonResponse
    {
        // Verificar autorización
        if ($profile->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado'
            ], 403);
        }

        $updatedProfile = $this->profileService->toggleProfileStatus($profile);

        return response()->json([
            'success' => true,
            'message' => 'Estado del perfil actualizado',
            'data' => [
                'is_active' => $updatedProfile->is_active
            ]
        ]);
    }
} 