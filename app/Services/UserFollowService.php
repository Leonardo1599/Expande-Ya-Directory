<?php

namespace App\Services;

use App\Models\User;
use App\Models\BusinessProfile;
use App\Models\UserFollow;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para gestión de seguimiento de perfiles (RF-11)
 * Patrón: Observer Pattern - Gestiona relaciones observador-observable
 * Patrón: Strategy Pattern - Diferentes estrategias de notificación
 * SOLID: Single Responsibility - Solo maneja seguimiento de perfiles
 * SOLID: Open/Closed - Abierto para nuevos tipos de seguimiento
 */
class UserFollowService
{
    /**
     * Seguir un perfil de negocio
     * Patrón: Observer Pattern - Registra observador
     */
    public function followProfile(
        User $user, 
        BusinessProfile $profile, 
        array $notificationPreferences = []
    ): UserFollow {
        return DB::transaction(function () use ($user, $profile, $notificationPreferences) {
            // Verificar que es un usuario final
            if (!$user->isEndUser()) {
                throw new \InvalidArgumentException(
                    'Solo usuarios finales pueden seguir perfiles de negocio'
                );
            }

            // Verificar que el perfil está activo
            if (!$profile->is_active) {
                throw new \InvalidArgumentException(
                    'No se puede seguir un perfil inactivo'
                );
            }

            // Buscar si ya existe el seguimiento
            $follow = UserFollow::where('user_id', $user->id)
                ->where('business_profile_id', $profile->id)
                ->first();

            if ($follow) {
                // Actualizar preferencias si ya existe
                $follow->updateNotificationPreferences($notificationPreferences);
                return $follow;
            }

            // Crear nuevo seguimiento
            return UserFollow::create([
                'user_id' => $user->id,
                'business_profile_id' => $profile->id,
                'email_notifications' => $notificationPreferences['email'] ?? true,
                'sms_notifications' => $notificationPreferences['sms'] ?? false,
                'push_notifications' => $notificationPreferences['push'] ?? true,
            ]);
        });
    }

    /**
     * Dejar de seguir un perfil
     * Patrón: Observer Pattern - Desregistra observador
     */
    public function unfollowProfile(User $user, BusinessProfile $profile): bool
    {
        $follow = UserFollow::where('user_id', $user->id)
            ->where('business_profile_id', $profile->id)
            ->first();

        if (!$follow) {
            return false;
        }

        return $follow->delete();
    }

    /**
     * Actualizar preferencias de notificación (RF-11)
     * Patrón: Command Pattern - Encapsula la actualización
     */
    public function updateNotificationPreferences(
        User $user, 
        BusinessProfile $profile, 
        array $preferences
    ): bool {
        $follow = UserFollow::where('user_id', $user->id)
            ->where('business_profile_id', $profile->id)
            ->first();

        if (!$follow) {
            throw new \InvalidArgumentException(
                'El usuario no sigue este perfil'
            );
        }

        return $follow->updateNotificationPreferences($preferences);
    }

    /**
     * Obtener perfiles seguidos por un usuario
     */
    public function getFollowedProfiles(User $user, int $page = 1, int $perPage = 20): array
    {
        $follows = $user->followedProfiles()
            ->with(['businessProfile.categories', 'businessProfile.socialNetworks'])
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'profiles' => $follows->items(),
            'pagination' => [
                'current_page' => $follows->currentPage(),
                'total_pages' => $follows->lastPage(),
                'total_items' => $follows->total(),
                'per_page' => $follows->perPage(),
            ]
        ];
    }

    /**
     * Obtener seguidores de un perfil de negocio
     */
    public function getProfileFollowers(
        BusinessProfile $profile, 
        int $page = 1, 
        int $perPage = 20
    ): array {
        $follows = UserFollow::where('business_profile_id', $profile->id)
            ->with('user')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'followers' => $follows->items(),
            'pagination' => [
                'current_page' => $follows->currentPage(),
                'total_pages' => $follows->lastPage(),
                'total_items' => $follows->total(),
                'per_page' => $follows->perPage(),
            ]
        ];
    }

    /**
     * Verificar si un usuario sigue un perfil
     */
    public function isFollowing(User $user, BusinessProfile $profile): bool
    {
        return UserFollow::where('user_id', $user->id)
            ->where('business_profile_id', $profile->id)
            ->exists();
    }

    /**
     * Obtener preferencias de notificación
     */
    public function getNotificationPreferences(User $user, BusinessProfile $profile): ?array
    {
        $follow = UserFollow::where('user_id', $user->id)
            ->where('business_profile_id', $profile->id)
            ->first();

        if (!$follow) {
            return null;
        }

        return [
            'email' => $follow->email_notifications,
            'sms' => $follow->sms_notifications,
            'push' => $follow->push_notifications,
        ];
    }

    /**
     * Obtener estadísticas de seguimiento para un usuario
     */
    public function getUserFollowStats(User $user): array
    {
        return [
            'total_following' => $user->followedProfiles()->count(),
            'with_email_notifications' => $user->followedProfiles()
                ->withEmailNotifications()->count(),
            'with_sms_notifications' => $user->followedProfiles()
                ->withSmsNotifications()->count(),
            'with_push_notifications' => $user->followedProfiles()
                ->withPushNotifications()->count(),
        ];
    }

    /**
     * Obtener estadísticas de seguidores para un perfil
     */
    public function getProfileFollowStats(BusinessProfile $profile): array
    {
        return [
            'total_followers' => $profile->followers()->count(),
            'email_followers' => $profile->followers()
                ->withEmailNotifications()->count(),
            'sms_followers' => $profile->followers()
                ->withSmsNotifications()->count(),
            'push_followers' => $profile->followers()
                ->withPushNotifications()->count(),
        ];
    }

    /**
     * Configurar preferencias globales de notificación para un usuario
     */
    public function updateGlobalNotificationPreferences(User $user, array $preferences): bool
    {
        return DB::transaction(function () use ($user, $preferences) {
            // Actualizar todas las relaciones de seguimiento
            $user->followedProfiles()->update([
                'email_notifications' => $preferences['email'] ?? true,
                'sms_notifications' => $preferences['sms'] ?? false,
                'push_notifications' => $preferences['push'] ?? true,
            ]);

            return true;
        });
    }
} 