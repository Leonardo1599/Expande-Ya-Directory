<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Patrón: Value Object - Las categorías son objetos de valor inmutables
 * SOLID: Single Responsibility - Solo maneja la entidad Categoría
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Eventos del modelo
    protected static function boot()
    {
        parent::boot();

        // Auto-generar slug
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name')) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    // Relaciones

    /**
     * Una categoría puede tener muchos perfiles de negocio
     */
    public function businessProfiles(): BelongsToMany
    {
        return $this->belongsToMany(BusinessProfile::class, 'business_category');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Obtener categorías con número de perfiles activos
     * Patrón: Builder Pattern - Construye consulta compleja
     */
    public function scopeWithProfileCount($query)
    {
        return $query->withCount(['businessProfiles' => function ($q) {
            $q->where('is_active', true);
        }]);
    }

    // Métodos auxiliares

    /**
     * Obtener clase CSS completa del icono
     */
    public function getIconClassAttribute(): string
    {
        return $this->icon ? "fas fa-{$this->icon}" : 'fas fa-folder';
    }

    /**
     * Obtener número de perfiles activos en esta categoría
     */
    public function getActiveProfilesCountAttribute(): int
    {
        return $this->businessProfiles()->where('is_active', true)->count();
    }
} 