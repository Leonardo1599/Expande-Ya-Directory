<?php

namespace App\Http\Controllers;

use App\Services\MapService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controlador para mapas interactivos (RF-7, RF-8)
 * Patrón: Controller Pattern - Maneja requests HTTP para mapas
 * SOLID: Single Responsibility - Solo maneja operaciones de mapas HTTP
 * SOLID: Dependency Injection - Inyecta MapService
 */
class MapController extends Controller
{
    private MapService $mapService;

    public function __construct(MapService $mapService)
    {
        $this->mapService = $mapService;
    }

    /**
     * Obtener marcadores para mapa (RF-7)
     * GET /api/map/markers
     */
    public function getMarkers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|between:1,50',
        ]);

        $markers = $this->mapService->getMapMarkers(
            $data['latitude'],
            $data['longitude'],
            $data['radius'] ?? 10
        );

        return response()->json([
            'success' => true,
            'data' => [
                'markers' => $markers,
                'bounds' => $this->mapService->calculateMapBounds($markers),
                'config' => $this->mapService->getMapConfig(),
            ]
        ]);
    }

    /**
     * Obtener configuración del mapa
     * GET /api/map/config
     */
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->mapService->getMapConfig()
        ]);
    }

    /**
     * Generar script JavaScript para el mapa
     * GET /api/map/script
     */
    public function getMapScript(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|between:1,50',
            'config' => 'nullable|array',
        ]);

        $markers = $this->mapService->getMapMarkers(
            $data['latitude'],
            $data['longitude'],
            $data['radius'] ?? 10
        );

        $script = $this->mapService->generateMapScript(
            $markers,
            $data['config'] ?? []
        );

        return response()->json([
            'success' => true,
            'data' => [
                'script' => $script,
                'markers_count' => count($markers),
            ]
        ]);
    }
} 