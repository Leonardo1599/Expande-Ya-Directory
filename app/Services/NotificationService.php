<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para manejo de notificaciones (RF-10, RF-11, RF-12)
 * Patrón: Strategy Pattern - Diferentes estrategias de envío (email, SMS, push)
 * Patrón: Observer Pattern - Observa cambios en perfiles para notificar
 * SOLID: Single Responsibility - Solo maneja notificaciones
 * SOLID: Open/Closed - Abierto para nuevos tipos de notificación
 */
class NotificationService
{
    // Estrategias de envío (Strategy Pattern)
    private array $strategies = [];

    public function __construct()
    {
        // Registrar estrategias de envío
        $this->strategies = [
            'email' => new EmailNotificationStrategy(),
            'sms' => new SmsNotificationStrategy(), 
            'push' => new PushNotificationStrategy(),
        ];
    }

    /**
     * Notificar a seguidores sobre actualización de perfil (RF-10)
     * Patrón: Observer Pattern - Notifica a todos los observadores
     */
    public function notifyProfileUpdate(BusinessProfile $profile, string $action = 'updated'): void
    {
        // Obtener seguidores que quieren notificaciones
        $followers = UserFollow::query()
            ->where('business_profile_id', $profile->id)
            ->with('user')
            ->get();

        $subject = $this->getSubjectForAction($action, $profile->name);
        $message = $this->getMessageForAction($action, $profile);

        foreach ($followers as $follow) {
            // Enviar notificaciones según preferencias del usuario
            foreach ($follow->enabledNotificationTypes as $type) {
                $this->scheduleNotification(
                    $follow->user,
                    $profile,
                    $type,
                    $subject,
                    $message
                );
            }
        }
    }

    /**
     * Programar notificación para envío asíncrono
     * Patrón: Command Pattern - Encapsula el comando de notificación
     */
    public function scheduleNotification(
        User $user, 
        BusinessProfile $profile, 
        string $type, 
        string $subject, 
        string $message
    ): Notification {
        return Notification::create([
            'user_id' => $user->id,
            'business_profile_id' => $profile->id,
            'type' => $type,
            'subject' => $subject,
            'message' => $message,
            'status' => Notification::STATUS_PENDING,
        ]);
    }

    /**
     * Procesar notificaciones pendientes
     * Patrón: Chain of Responsibility - Procesa en cadena
     */
    public function processPendingNotifications(int $limit = 50): array
    {
        $notifications = Notification::pending()
            ->with(['user', 'businessProfile'])
            ->limit($limit)
            ->get();

        $results = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        foreach ($notifications as $notification) {
            $results['processed']++;
            
            if ($this->sendNotification($notification)) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Enviar notificación individual
     * Patrón: Strategy Pattern - Usa estrategia según tipo
     */
    private function sendNotification(Notification $notification): bool
    {
        try {
            $strategy = $this->strategies[$notification->type] ?? null;
            
            if (!$strategy) {
                throw new \InvalidArgumentException("Tipo de notificación no soportado: {$notification->type}");
            }

            $sent = $strategy->send($notification);
            
            if ($sent) {
                $notification->markAsSent();
                return true;
            } else {
                $notification->markAsFailed('Error en estrategia de envío');
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error("Error enviando notificación {$notification->id}: " . $e->getMessage());
            $notification->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Obtener historial de notificaciones de un usuario (RF-12)
     */
    public function getUserNotificationHistory(User $user, int $page = 1, int $perPage = 20): array
    {
        $notifications = $user->notifications()
            ->with('businessProfile')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'notifications' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'total_pages' => $notifications->lastPage(),
                'total_items' => $notifications->total(),
                'per_page' => $notifications->perPage(),
            ]
        ];
    }

    /**
     * Actualizar preferencias de notificación (RF-11)
     */
    public function updateUserNotificationPreferences(
        User $user, 
        BusinessProfile $profile, 
        array $preferences
    ): bool {
        $follow = UserFollow::where('user_id', $user->id)
            ->where('business_profile_id', $profile->id)
            ->first();

        if (!$follow) {
            // Crear relación de seguimiento si no existe
            $follow = UserFollow::create([
                'user_id' => $user->id,
                'business_profile_id' => $profile->id,
                'email_notifications' => false,
                'sms_notifications' => false,
                'push_notifications' => false,
            ]);
        }

        return $follow->updateNotificationPreferences($preferences);
    }

    /**
     * Obtener estadísticas de notificaciones
     */
    public function getNotificationStats(BusinessProfile $profile): array
    {
        return [
            'total_followers' => $profile->followers()->count(),
            'email_followers' => $profile->followers()->withEmailNotifications()->count(),
            'sms_followers' => $profile->followers()->withSmsNotifications()->count(),
            'push_followers' => $profile->followers()->withPushNotifications()->count(),
            'recent_notifications' => $profile->notifications()->recent(7)->count(),
        ];
    }

    // Métodos auxiliares

    private function getSubjectForAction(string $action, string $profileName): string
    {
        $subjects = [
            'created' => "Nuevo perfil: {$profileName}",
            'updated' => "Actualización en: {$profileName}",
            'deleted' => "Perfil eliminado: {$profileName}",
        ];

        return $subjects[$action] ?? "Notificación de: {$profileName}";
    }

    private function getMessageForAction(string $action, BusinessProfile $profile): string
    {
        $messages = [
            'created' => "El perfil {$profile->name} se ha registrado en el directorio. ¡Echa un vistazo!",
            'updated' => "El perfil {$profile->name} ha actualizado su información. Revisa las novedades.",
            'deleted' => "El perfil {$profile->name} ya no está disponible en el directorio.",
        ];

        return $messages[$action] ?? "Hay novedades en el perfil {$profile->name}.";
    }
}

/**
 * Estrategia para notificaciones por email
 * Patrón: Strategy Pattern - Implementa algoritmo específico de envío
 */
class EmailNotificationStrategy
{
    public function send(Notification $notification): bool
    {
        try {
            // Simular envío de email - En producción usar Mail::send()
            // Mail::to($notification->user->email)->send(new ProfileUpdateNotification($notification));
            
            // Por ahora, solo log
            Log::info("Email enviado a {$notification->user->email}: {$notification->subject}");
            
            return true;
        } catch (\Exception $e) {
            Log::error("Error enviando email: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Estrategia para notificaciones por SMS
 */
class SmsNotificationStrategy
{
    public function send(Notification $notification): bool
    {
        try {
            // Simular envío de SMS - En producción usar servicio como Twilio
            Log::info("SMS enviado a {$notification->user->phone}: {$notification->subject}");
            
            return true;
        } catch (\Exception $e) {
            Log::error("Error enviando SMS: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Estrategia para notificaciones push
 */
class PushNotificationStrategy
{
    public function send(Notification $notification): bool
    {
        try {
            // Simular envío de push - En producción usar FCM o similar
            Log::info("Push enviado a usuario {$notification->user->id}: {$notification->subject}");
            
            return true;
        } catch (\Exception $e) {
            Log::error("Error enviando push: " . $e->getMessage());
            return false;
        }
    }
} 