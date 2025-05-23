<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * Servicio para gestión de perfiles de negocio
 * Patrón: Repository Pattern - Abstrae el acceso a datos
 * Patrón: Service Layer - Lógica de negocio separada del controlador
 * SOLID: Single Responsibility - Solo maneja operaciones de perfiles
 * SOLID: Dependency Inversion - Depende de abstracciones, no implementaciones concretas
 */
class BusinessProfileService
{
    /**
     * Crear nuevo perfil de negocio (RF-3)
     * Patrón: Factory Method - Encapsula la creación de perfiles
     */
    public function createProfile(User $user, array $data): BusinessProfile
    {
        return DB::transaction(function () use ($user, $data) {
            // Validar que el usuario puede crear perfil
            if (!$user->isBusinessUser()) {
                throw new \InvalidArgumentException('Solo usuarios de tipo business pueden crear perfiles');
            }

            // Crear el perfil
            $profile = $user->businessProfile()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'website' => $data['website'] ?? null,
            ]);

            // Asociar categorías si se proporcionan
            if (!empty($data['categories'])) {
                $profile->categories()->attach($data['categories']);
            }

            return $profile;
        });
    }

    /**
     * Actualizar perfil de negocio (RF-3)
     * Patrón: Command Pattern - Encapsula la operación de actualización
     */
    public function updateProfile(BusinessProfile $profile, array $data): BusinessProfile
    {
        return DB::transaction(function () use ($profile, $data) {
            // Actualizar datos del perfil
            $profile->update([
                'name' => $data['name'] ?? $profile->name,
                'description' => $data['description'] ?? $profile->description,
                'latitude' => $data['latitude'] ?? $profile->latitude,
                'longitude' => $data['longitude'] ?? $profile->longitude,
                'address' => $data['address'] ?? $profile->address,
                'phone' => $data['phone'] ?? $profile->phone,
                'email' => $data['email'] ?? $profile->email,
                'website' => $data['website'] ?? $profile->website,
            ]);

            // Actualizar categorías si se proporcionan
            if (isset($data['categories'])) {
                $profile->categories()->sync($data['categories']);
            }

            return $profile->fresh();
        });
    }

    /**
     * Subir y asignar logo al perfil (RF-4)
     * SOLID: Single Responsibility - Solo maneja la subida de archivos
     */
    public function uploadLogo(BusinessProfile $profile, UploadedFile $file): string
    {
        // Eliminar logo anterior si existe
        if ($profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
        }

        // Subir nuevo logo
        $path = $file->store('logos', 'public');
        
        // Actualizar perfil
        $profile->update(['logo_path' => $path]);

        return $path;
    }

    /**
     * Búsqueda de perfiles con filtros (RF-5, RF-6)
     * Patrón: Builder Pattern - Construye consultas complejas dinámicamente
     */
    public function searchProfiles(array $filters): LengthAwarePaginator
    {
        $query = BusinessProfile::query()
            ->with(['categories', 'socialNetworks'])
            ->active();

        // Filtro por categoría
        if (!empty($filters['category_id'])) {
            $query->byCategory($filters['category_id']);
        }

        // Búsqueda por texto
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Búsqueda geográfica (RF-6)
        if (!empty($filters['latitude']) && !empty($filters['longitude'])) {
            $radius = $filters['radius'] ?? 10; // Radio por defecto 10km
            $radius = max(1, min(50, $radius)); // Limitado entre 1-50km
            
            $query->withinRadius(
                $filters['latitude'], 
                $filters['longitude'], 
                $radius
            );
        }

        // Paginación
        $perPage = $filters['per_page'] ?? 12;
        $perPage = max(6, min(50, $perPage)); // Limitado entre 6-50

        return $query->paginate($perPage);
    }

    /**
     * Obtener perfiles cercanos para mapa (RF-7)
     * Patrón: Strategy Pattern - Algoritmo específico para mapas
     */
    public function getNearbyProfiles(float $latitude, float $longitude, int $radius = 10): Collection
    {
        return BusinessProfile::query()
            ->with(['categories'])
            ->active()
            ->withinRadius($latitude, $longitude, $radius)
            ->limit(100) // Límite para rendimiento
            ->get();
    }

    /**
     * Obtener perfil por slug (RF-8)
     */
    public function getProfileBySlug(string $slug): ?BusinessProfile
    {
        return BusinessProfile::query()
            ->with(['categories', 'socialNetworks', 'user'])
            ->where('slug', $slug)
            ->active()
            ->first();
    }

    /**
     * Eliminar perfil de negocio (RF-3)
     * Patrón: Command Pattern - Encapsula la operación de eliminación
     */
    public function deleteProfile(BusinessProfile $profile): bool
    {
        return DB::transaction(function () use ($profile) {
            // Eliminar logo si existe
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }

            // Eliminar perfil (las relaciones se eliminan por cascada)
            return $profile->delete();
        });
    }

    /**
     * Activar/desactivar perfil
     * Patrón: State Pattern - Cambio de estado del perfil
     */
    public function toggleProfileStatus(BusinessProfile $profile): BusinessProfile
    {
        $profile->update(['is_active' => !$profile->is_active]);
        return $profile->fresh();
    }

    /**
     * Obtener estadísticas del perfil
     * SOLID: Single Responsibility - Solo calcula estadísticas
     */
    public function getProfileStats(BusinessProfile $profile): array
    {
        return [
            'followers_count' => $profile->followers()->count(),
            'categories_count' => $profile->categories()->count(),
            'social_networks_count' => $profile->socialNetworks()->active()->count(),
            'total_views' => 0, // Implementar si se requiere tracking de vistas
        ];
    }
} 