<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Patrón: Factory Method - Permite crear diferentes tipos de usuarios
 * SOLID: Single Responsibility - Maneja únicamente la entidad Usuario
 * SOLID: Open/Closed - Abierto para extensión (nuevos tipos de usuario)
 */
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'phone',
        'latitude',
        'longitude',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_active' => 'boolean',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'user_type' => $this->user_type,
            'email' => $this->email,
        ];
    }

    // Factory Methods para tipos de usuario
    public function isBusinessUser(): bool
    {
        return $this->user_type === 'business';
    }

    public function isEndUser(): bool
    {
        return $this->user_type === 'end_user';
    }

    // Relaciones

    /**
     * Un usuario de tipo business puede tener un perfil de negocio
     * Patrón: Strategy - Diferente comportamiento según tipo de usuario
     */
    public function businessProfile(): HasOne
    {
        return $this->hasOne(BusinessProfile::class);
    }

    /**
     * Un usuario puede seguir muchos perfiles de negocio
     */
    public function followedProfiles(): HasMany
    {
        return $this->hasMany(UserFollow::class);
    }

    /**
     * Notificaciones recibidas por el usuario
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // Scopes para consultas optimizadas

    public function scopeBusinessUsers($query)
    {
        return $query->where('user_type', 'business');
    }

    public function scopeEndUsers($query)
    {
        return $query->where('user_type', 'end_user');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para búsqueda geográfica usando fórmula Haversine
     * SOLID: Single Responsibility - Solo calcula distancia
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
        )->having('distance', '<', $radiusKm);
    }
}
