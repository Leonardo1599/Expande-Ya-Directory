<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Patrón: Value Object - Las redes sociales son objetos de valor
 * Patrón: Strategy Pattern - Diferente validación por plataforma
 * SOLID: Single Responsibility - Solo maneja redes sociales
 */
class SocialNetwork extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_profile_id',
        'platform',
        'url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Patrones de URL por plataforma (Strategy Pattern)
    protected static array $urlPatterns = [
        'facebook' => '/^https?:\/\/(www\.)?facebook\.com\/[\w\.\-]+\/?$/i',
        'instagram' => '/^https?:\/\/(www\.)?instagram\.com\/[\w\.\-]+\/?$/i',
        'twitter' => '/^https?:\/\/(www\.)?(twitter\.com|x\.com)\/[\w\.\-]+\/?$/i',
        'linkedin' => '/^https?:\/\/(www\.)?linkedin\.com\/(in|company)\/[\w\.\-]+\/?$/i',
        'youtube' => '/^https?:\/\/(www\.)?youtube\.com\/(channel\/|c\/|user\/)?[\w\.\-]+\/?$/i',
        'tiktok' => '/^https?:\/\/(www\.)?tiktok\.com\/@[\w\.\-]+\/?$/i',
        'whatsapp' => '/^https?:\/\/(wa\.me|api\.whatsapp\.com)\/[\d\+]+(\?.*)?$/i',
    ];

    // Relaciones

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    // Métodos de validación (RF-9)

    /**
     * Validar URL según la plataforma
     * Patrón: Strategy Pattern - Algoritmo específico por plataforma
     */
    public function isValidUrl(): bool
    {
        $pattern = self::$urlPatterns[$this->platform] ?? null;
        
        if (!$pattern) {
            return filter_var($this->url, FILTER_VALIDATE_URL) !== false;
        }

        return preg_match($pattern, $this->url) === 1;
    }

    /**
     * Obtener icono de la plataforma
     * Patrón: Factory Method - Crea iconos específicos por plataforma
     */
    public function getIconClassAttribute(): string
    {
        $icons = [
            'facebook' => 'fab fa-facebook',
            'instagram' => 'fab fa-instagram',
            'twitter' => 'fab fa-twitter',
            'linkedin' => 'fab fa-linkedin',
            'youtube' => 'fab fa-youtube',
            'tiktok' => 'fab fa-tiktok',
            'whatsapp' => 'fab fa-whatsapp',
        ];

        return $icons[$this->platform] ?? 'fas fa-link';
    }

    /**
     * Obtener color de la plataforma
     */
    public function getColorAttribute(): string
    {
        $colors = [
            'facebook' => '#1877F2',
            'instagram' => '#E4405F',
            'twitter' => '#1DA1F2',
            'linkedin' => '#0A66C2',
            'youtube' => '#FF0000',
            'tiktok' => '#000000',
            'whatsapp' => '#25D366',
        ];

        return $colors[$this->platform] ?? '#000000';
    }

    /**
     * Formatear URL para mostrar
     * Patrón: Template Method - Define estructura de formateo
     */
    public function getDisplayUrlAttribute(): string
    {
        // Remover protocolo y www para mostrar
        return preg_replace('/^https?:\/\/(www\.)?/', '', $this->url);
    }

    /**
     * Obtener atributos HTML para abrir en nueva pestaña
     * SOLID: Single Responsibility - Solo genera atributos HTML
     */
    public function getLinkAttributesAttribute(): string
    {
        return 'target="_blank" rel="noopener noreferrer"';
    }
} 