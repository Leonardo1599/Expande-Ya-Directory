<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Patrón: Observer Pattern - Para seguimiento de perfiles
 * SOLID: Single Responsibility - Solo maneja las relaciones de seguimiento
 */
class UserFollow extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_profile_id',
        'email_notifications',
        'sms_notifications',
        'push_notifications',
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'push_notifications' => 'boolean',
    ];

    // Relaciones

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    // Scopes

    /**
     * Usuarios que quieren notificaciones por email
     */
    public function scopeWithEmailNotifications($query)
    {
        return $query->where('email_notifications', true);
    }

    /**
     * Usuarios que quieren notificaciones por SMS
     */
    public function scopeWithSmsNotifications($query)
    {
        return $query->where('sms_notifications', true);
    }

    /**
     * Usuarios que quieren notificaciones push
     */
    public function scopeWithPushNotifications($query)
    {
        return $query->where('push_notifications', true);
    }

    // Métodos auxiliares

    /**
     * Verificar si el usuario quiere algún tipo de notificación
     * SOLID: Single Responsibility - Solo verifica preferencias
     */
    public function wantsNotifications(): bool
    {
        return $this->email_notifications || 
               $this->sms_notifications || 
               $this->push_notifications;
    }

    /**
     * Obtener tipos de notificación habilitados
     */
    public function getEnabledNotificationTypesAttribute(): array
    {
        $types = [];
        
        if ($this->email_notifications) $types[] = 'email';
        if ($this->sms_notifications) $types[] = 'sms';
        if ($this->push_notifications) $types[] = 'push';
        
        return $types;
    }

    /**
     * Actualizar todas las preferencias de notificación
     * Patrón: Command Pattern - Encapsula la operación de actualización
     */
    public function updateNotificationPreferences(array $preferences): bool
    {
        return $this->update([
            'email_notifications' => $preferences['email'] ?? false,
            'sms_notifications' => $preferences['sms'] ?? false,
            'push_notifications' => $preferences['push'] ?? false,
        ]);
    }
} 