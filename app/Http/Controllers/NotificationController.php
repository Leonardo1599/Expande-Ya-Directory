<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Services\UserFollowService;
use App\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador de notificaciones (RF-10, RF-11, RF-12)
 * Patrón: Controller Pattern - Maneja requests HTTP para notificaciones
 * SOLID: Single Responsibility - Solo maneja notificaciones HTTP
 * SOLID: Dependency Injection - Inyecta servicios necesarios
 */
class NotificationController extends Controller
{
    private NotificationService $notificationService;
    private UserFollowService $followService;

    public function __construct(
        NotificationService $notificationService,
        UserFollowService $followService
    ) {
        $this->notificationService = $notificationService;
        $this->followService = $followService;
        $this->middleware('auth:api');
    }

    /**
     * Seguir un perfil de negocio (RF-11)
     */
    public function followProfile(Request $request, BusinessProfile $profile): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'push_notifications' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $preferences = [
                'email' => $request->get('email_notifications', true),
                'sms' => $request->get('sms_notifications', false),
                'push' => $request->get('push_notifications', true),
            ];

            $follow = $this->followService->followProfile(
                $request->user(),
                $profile,
                $preferences
            );

            return response()->json([
                'message' => 'Perfil seguido exitosamente',
                'follow' => $follow,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al seguir el perfil',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Dejar de seguir un perfil
     */
    public function unfollowProfile(Request $request, BusinessProfile $profile): JsonResponse
    {
        try {
            $success = $this->followService->unfollowProfile(
                $request->user(),
                $profile
            );

            if ($success) {
                return response()->json([
                    'message' => 'Perfil eliminado de seguidos'
                ]);
            }

            return response()->json([
                'message' => 'No seguías este perfil'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al dejar de seguir',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Actualizar preferencias de notificación (RF-11)
     */
    public function updateNotificationPreferences(
        Request $request, 
        BusinessProfile $profile
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'required|boolean',
            'sms_notifications' => 'required|boolean',
            'push_notifications' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $preferences = [
                'email' => $request->email_notifications,
                'sms' => $request->sms_notifications,
                'push' => $request->push_notifications,
            ];

            $success = $this->followService->updateNotificationPreferences(
                $request->user(),
                $profile,
                $preferences
            );

            if ($success) {
                return response()->json([
                    'message' => 'Preferencias actualizadas'
                ]);
            }

            return response()->json([
                'message' => 'Error al actualizar preferencias'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obtener preferencias de notificación actuales
     */
    public function getNotificationPreferences(
        Request $request, 
        BusinessProfile $profile
    ): JsonResponse {
        $preferences = $this->followService->getNotificationPreferences(
            $request->user(),
            $profile
        );

        if ($preferences === null) {
            return response()->json([
                'message' => 'No sigues este perfil'
            ], 404);
        }

        return response()->json([
            'preferences' => $preferences
        ]);
    }

    /**
     * Obtener historial de notificaciones (RF-12)
     */
    public function getNotificationHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Parámetros inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        $history = $this->notificationService->getUserNotificationHistory(
            $request->user(),
            $page,
            $perPage
        );

        return response()->json($history);
    }

    /**
     * Obtener perfiles seguidos
     */
    public function getFollowedProfiles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Parámetros inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        $profiles = $this->followService->getFollowedProfiles(
            $request->user(),
            $page,
            $perPage
        );

        return response()->json($profiles);
    }

    /**
     * Obtener estadísticas de seguimiento del usuario
     */
    public function getUserFollowStats(Request $request): JsonResponse
    {
        $stats = $this->followService->getUserFollowStats($request->user());

        return response()->json([
            'stats' => $stats
        ]);
    }

    /**
     * Verificar si sigue un perfil
     */
    public function checkFollowStatus(Request $request, BusinessProfile $profile): JsonResponse
    {
        $isFollowing = $this->followService->isFollowing(
            $request->user(),
            $profile
        );

        return response()->json([
            'is_following' => $isFollowing
        ]);
    }

    /**
     * Configurar preferencias globales de notificación
     */
    public function updateGlobalNotificationPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'required|boolean',
            'sms_notifications' => 'required|boolean',
            'push_notifications' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $preferences = [
                'email' => $request->email_notifications,
                'sms' => $request->sms_notifications,
                'push' => $request->push_notifications,
            ];

            $success = $this->followService->updateGlobalNotificationPreferences(
                $request->user(),
                $preferences
            );

            if ($success) {
                return response()->json([
                    'message' => 'Preferencias globales actualizadas'
                ]);
            }

            return response()->json([
                'message' => 'Error al actualizar preferencias'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar',
                'error' => $e->getMessage()
            ], 400);
        }
    }
} 