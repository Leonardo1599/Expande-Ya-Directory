<?php

namespace App\Services;

use App\Models\BusinessProfile;
use Illuminate\Database\Eloquent\Collection;

/**
 * Servicio para manejo de mapas interactivos (RF-7, RF-8)
 * Patrón: Strategy Pattern - Diferentes estrategias de mapas (Google, OSM, etc.)
 * Patrón: Factory Method - Crea marcadores específicos por tipo de negocio
 * SOLID: Single Responsibility - Solo maneja operaciones de mapas
 * SOLID: Open/Closed - Abierto para nuevos proveedores de mapas
 */
class MapService
{
    /**
     * Obtener marcadores para mapa (RF-7)
     * Patrón: Builder Pattern - Construye marcadores con datos específicos
     */
    public function getMapMarkers(float $latitude, float $longitude, int $radius = 10): array
    {
        $profiles = BusinessProfile::query()
            ->with(['categories'])
            ->active()
            ->withinRadius($latitude, $longitude, $radius)
            ->limit(100)
            ->get();

        return $profiles->map(function ($profile) {
            return $this->createMarker($profile);
        })->toArray();
    }

    /**
     * Crear marcador individual (RF-8)
     * Patrón: Factory Method - Crea marcadores según tipo de negocio
     */
    private function createMarker(BusinessProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'slug' => $profile->slug,
            'position' => [
                'lat' => (float) $profile->latitude,
                'lng' => (float) $profile->longitude,
            ],
            'title' => $profile->name,
            'category' => $profile->categories->first()?->name ?? 'General',
            'category_color' => $this->getCategoryColor($profile->categories->first()),
            'distance' => $profile->distance ?? 0,
            'logo_url' => $profile->logo_url,
            'address' => $profile->address,
            'phone' => $profile->phone,
            'website' => $profile->website,
            'profile_url' => route('profiles.show', $profile->slug),
            'popup_content' => $this->generatePopupContent($profile),
        ];
    }

    /**
     * Generar contenido del popup (RF-8)
     * Patrón: Template Method - Define estructura del popup
     */
    private function generatePopupContent(BusinessProfile $profile): string
    {
        $logoHtml = $profile->logo_url 
            ? "<img src='{$profile->logo_url}' alt='{$profile->name}' class='popup-logo' />"
            : '';

        $categoryHtml = $profile->categories->isNotEmpty()
            ? "<span class='popup-category'>{$profile->categories->first()->name}</span>"
            : '';

        $distanceHtml = isset($profile->distance)
            ? "<span class='popup-distance'>" . round($profile->distance, 1) . " km</span>"
            : '';

        $contactInfo = [];
        if ($profile->phone) {
            $contactInfo[] = "<span class='popup-phone'>📞 {$profile->phone}</span>";
        }
        if ($profile->email) {
            $contactInfo[] = "<span class='popup-email'>✉️ {$profile->email}</span>";
        }
        if ($profile->website) {
            $contactInfo[] = "<a href='{$profile->website}' target='_blank' class='popup-website'>🌐 Sitio Web</a>";
        }

        $contactHtml = !empty($contactInfo) 
            ? "<div class='popup-contact'>" . implode('<br>', $contactInfo) . "</div>"
            : '';

        return "
            <div class='map-popup'>
                <div class='popup-header'>
                    {$logoHtml}
                    <div class='popup-title-section'>
                        <h3 class='popup-title'>{$profile->name}</h3>
                        {$categoryHtml} {$distanceHtml}
                    </div>
                </div>
                <div class='popup-body'>
                    <p class='popup-description'>" . str_limit($profile->description, 100) . "</p>
                    {$contactHtml}
                    <div class='popup-actions'>
                        <a href='{$profile->public_url}' class='btn-view-profile'>Ver Perfil Completo</a>
                    </div>
                </div>
            </div>
        ";
    }

    /**
     * Obtener color por categoría
     * Patrón: Strategy Pattern - Estrategia de colores por categoría
     */
    private function getCategoryColor($category): string
    {
        if (!$category) {
            return '#6B7280'; // Color gris por defecto
        }

        // Mapeo de colores por categoría
        $colorMap = [
            'Restaurante' => '#EF4444',
            'Tecnología' => '#3B82F6',
            'Salud' => '#10B981',
            'Educación' => '#F59E0B',
            'Belleza' => '#EC4899',
            'Deporte' => '#8B5CF6',
            'Automotriz' => '#6B7280',
            'Inmobiliaria' => '#14B8A6',
        ];

        return $colorMap[$category->name] ?? '#6B7280';
    }

    /**
     * Obtener configuración del mapa
     * SOLID: Single Responsibility - Solo configuración de mapas
     */
    public function getMapConfig(): array
    {
        return [
            'default_zoom' => 13,
            'min_zoom' => 8,
            'max_zoom' => 18,
            'cluster_max_zoom' => 15,
            'default_center' => [
                'lat' => -34.6037,
                'lng' => -58.3816, // Buenos Aires por defecto
            ],
            'styles' => [
                'marker_size' => 'medium',
                'cluster_styles' => [
                    'textColor' => 'white',
                    'textSize' => 12,
                    'width' => 40,
                    'height' => 40,
                ],
            ],
        ];
    }

    /**
     * Calcular límites del mapa para mostrar todos los marcadores
     * Patrón: Command Pattern - Encapsula el cálculo
     */
    public function calculateMapBounds(array $markers): array
    {
        if (empty($markers)) {
            return $this->getMapConfig()['default_center'];
        }

        $latitudes = array_column(array_column($markers, 'position'), 'lat');
        $longitudes = array_column(array_column($markers, 'position'), 'lng');

        return [
            'north' => max($latitudes),
            'south' => min($latitudes),
            'east' => max($longitudes),
            'west' => min($longitudes),
        ];
    }

    /**
     * Generar código JavaScript para inicializar el mapa
     * Patrón: Builder Pattern - Construye el código JS dinámicamente
     */
    public function generateMapScript(array $markers, array $config = []): string
    {
        $config = array_merge($this->getMapConfig(), $config);
        $markersJson = json_encode($markers);
        $configJson = json_encode($config);

        return "
            function initializeMap() {
                const mapConfig = {$configJson};
                const markers = {$markersJson};
                
                // Inicializar mapa
                const map = new google.maps.Map(document.getElementById('map'), {
                    zoom: mapConfig.default_zoom,
                    center: mapConfig.default_center,
                    minZoom: mapConfig.min_zoom,
                    maxZoom: mapConfig.max_zoom,
                });

                // Crear marcadores
                const mapMarkers = [];
                const infoWindow = new google.maps.InfoWindow();

                markers.forEach(markerData => {
                    const marker = new google.maps.Marker({
                        position: markerData.position,
                        map: map,
                        title: markerData.title,
                        icon: {
                            url: '/icons/marker-' + markerData.category.toLowerCase() + '.png',
                            scaledSize: new google.maps.Size(32, 32),
                        }
                    });

                    marker.addListener('click', () => {
                        infoWindow.setContent(markerData.popup_content);
                        infoWindow.open(map, marker);
                    });

                    mapMarkers.push(marker);
                });

                // Clustering si hay muchos marcadores
                if (markers.length > 10) {
                    new MarkerClusterer(map, mapMarkers, {
                        maxZoom: mapConfig.cluster_max_zoom,
                        styles: [mapConfig.styles.cluster_styles]
                    });
                }

                // Ajustar vista para mostrar todos los marcadores
                if (markers.length > 0) {
                    const bounds = new google.maps.LatLngBounds();
                    markers.forEach(marker => {
                        bounds.extend(marker.position);
                    });
                    map.fitBounds(bounds);
                }

                return map;
            }

            // Función para filtrar marcadores por categoría
            function filterMarkersByCategory(category) {
                // Implementar filtrado dinámico
                console.log('Filtrando por categoría:', category);
            }
        ";
    }
} 