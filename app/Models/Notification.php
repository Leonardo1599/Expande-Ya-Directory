<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para historial de notificaciones (RF-12)
 * Patrón: State Pattern - Maneja diferentes estados de notificación
 * SOLID: Single Responsibility - Solo maneja el historial de notificaciones
 */
class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_profile_id',
        'type',
        'subject',
        'message',
        'status',
        'sent_at',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Estados posibles (State Pattern)
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    // Tipos de notificación
    const TYPE_EMAIL = 'email';
    const TYPE_SMS = 'sms';
    const TYPE_PUSH = 'push';

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

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Métodos de estado (State Pattern)

    /**
     * Marcar notificación como enviada
     */
    public function markAsSent(): bool
    {
        return $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Marcar notificación como fallida
     */
    public function markAsFailed(string $reason = null): bool
    {
        $metadata = $this->metadata ?? [];
        if ($reason) {
            $metadata['failure_reason'] = $reason;
        }

        return $this->update([
            'status' => self::STATUS_FAILED,
            'metadata' => $metadata,
        ]);
    }

    // Métodos auxiliares

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Obtener icono del tipo de notificación
     * Patrón: Factory Method - Crea iconos específicos por tipo
     */
    public function getIconClassAttribute(): string
    {
        $icons = [
            self::TYPE_EMAIL => 'fas fa-envelope',
            self::TYPE_SMS => 'fas fa-sms',
            self::TYPE_PUSH => 'fas fa-bell',
        ];

        return $icons[$this->type] ?? 'fas fa-info';
    }

    /**
     * Obtener color del estado
     */
    public function getStatusColorAttribute(): string
    {
        $colors = [
            self::STATUS_PENDING => 'warning',
            self::STATUS_SENT => 'success',
            self::STATUS_FAILED => 'danger',
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    /**
     * Obtener etiqueta del estado en español
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_SENT => 'Enviada',
            self::STATUS_FAILED => 'Fallida',
        ];

        return $labels[$this->status] ?? 'Desconocido';
    }

    /**
     * Obtener etiqueta del tipo en español
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            self::TYPE_EMAIL => 'Email',
            self::TYPE_SMS => 'SMS',
            self::TYPE_PUSH => 'Notificación Push',
        ];

        return $labels[$this->type] ?? 'Desconocido';
    }
} 