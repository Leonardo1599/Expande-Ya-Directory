<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Patrón: Repository Pattern - Encapsula la lógica de acceso a datos
 * Patrón: Builder Pattern - Para construcción de consultas complejas
 * SOLID: Single Responsibility - Maneja únicamente los perfiles de negocio
 */
class BusinessProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'logo_path',
        'latitude',
        'longitude',
        'address',
        'phone',
        'email',
        'website',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    // Eventos del modelo - Patrón Observer
    protected static function boot()
    {
        parent::boot();

        // Auto-generar slug al crear
        static::creating(function ($profile) {
            if (empty($profile->slug)) {
                $profile->slug = Str::slug($profile->name);
            }
        });

        // Actualizar slug al modificar nombre
        static::updating(function ($profile) {
            if ($profile->isDirty('name')) {
                $profile->slug = Str::slug($profile->name);
            }
        });
    }

    // Relaciones

    /**
     * Perfil pertenece a un usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un perfil puede tener muchas categorías (Many-to-Many)
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'business_category');
    }

    /**
     * Redes sociales del perfil
     */
    public function socialNetworks(): HasMany
    {
        return $this->hasMany(SocialNetwork::class);
    }

    /**
     * Usuarios que siguen este perfil
     */
    public function followers(): HasMany
    {
        return $this->hasMany(UserFollow::class);
    }

    /**
     * Notificaciones relacionadas con este perfil
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // Scopes para consultas optimizadas

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('categories.id', $categoryId);
        });
    }

    /**
     * Búsqueda geográfica con radio configurable (RF-6)
     * Patrón: Strategy Pattern - Algoritmo de búsqueda intercambiable
     */
    public function scopeWithinRadius($query, $latitude, $longitude, $radiusKm = 10)
    {
        return $query->selectRaw(
            "*, ( 6371 * acos( cos( radians(?) ) * 
            cos( radians( latitude ) ) * 
            cos( radians( longitude ) - radians(?) ) + 
            sin( radians(?) ) * 
            sin( radians( latitude ) ) ) ) AS distance",
            [$latitude, $longitude, $latitude]
        )->having('distance', '<', $radiusKm)
        ->orderBy('distance');
    }

    /**
     * Búsqueda por texto en nombre y descripción
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    // Métodos auxiliares

    /**
     * Obtener URL completa del logo
     * Patrón: Template Method - Define la estructura para obtener URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/' . $this->logo_path) : null;
    }

    /**
     * Validar si tiene coordenadas válidas
     * SOLID: Interface Segregation - Solo expone métodos relevantes
     */
    public function hasValidCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Calcular distancia a un punto específico
     * Patrón: Command Pattern - Encapsula el cálculo como comando
     */
    public function distanceTo(float $latitude, float $longitude): float
    {
        if (!$this->hasValidCoordinates()) {
            return 0;
        }

        $earthRadius = 6371; // Radio de la Tierra en km

        $latDelta = deg2rad($latitude - $this->latitude);
        $lonDelta = deg2rad($longitude - $this->longitude);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Obtener URL de perfil público
     */
    public function getPublicUrlAttribute(): string
    {
        return route('profiles.show', $this->slug);
    }
} 