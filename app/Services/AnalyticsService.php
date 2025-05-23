<?php

namespace App\Services;

use App\Models\User;
use App\Models\BusinessProfile;
use App\Models\Notification;
use App\Models\UserFollow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Servicio de análisis y estadísticas
 * Patrón: Strategy Pattern - Diferentes estrategias de cálculo de métricas
 * Patrón: Decorator Pattern - Decora datos con estadísticas adicionales
 * SOLID: Single Responsibility - Solo maneja estadísticas y análisis
 * SOLID: Open/Closed - Abierto para nuevas métricas
 */
class AnalyticsService
{
    /**
     * Obtener estadísticas generales del sistema
     * Patrón: Facade Pattern - Simplifica el acceso a múltiples estadísticas
     */
    public function getSystemStats(): array
    {
        return Cache::remember('system_stats', 300, function () {
            return [
                'users' => $this->getUserStats(),
                'profiles' => $this->getProfileStats(),
                'notifications' => $this->getNotificationStats(),
                'geography' => $this->getGeographyStats(),
                'activity' => $this->getActivityStats(),
            ];
        });
    }

    /**
     * Estadísticas de usuarios
     * SOLID: Single Responsibility - Solo calcula stats de usuarios
     */
    private function getUserStats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::active()->count(),
            'business_users' => User::businessUsers()->count(),
            'end_users' => User::endUsers()->count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'registered_today' => User::whereDate('created_at', today())->count(),
            'registered_this_week' => User::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'registered_this_month' => User::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }

    /**
     * Estadísticas de perfiles de negocio
     */
    private function getProfileStats(): array
    {
        $categoryStats = BusinessProfile::join('business_category', 'business_profiles.id', '=', 'business_category.business_profile_id')
            ->join('categories', 'business_category.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('COUNT(*) as count'))
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->pluck('count', 'name')
            ->toArray();

        return [
            'total' => BusinessProfile::count(),
            'active' => BusinessProfile::active()->count(),
            'with_logo' => BusinessProfile::whereNotNull('logo_path')->count(),
            'with_website' => BusinessProfile::whereNotNull('website')->count(),
            'with_social_networks' => BusinessProfile::whereHas('socialNetworks')->count(),
            'by_category' => $categoryStats,
            'created_today' => BusinessProfile::whereDate('created_at', today())->count(),
            'created_this_week' => BusinessProfile::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'avg_followers' => round(UserFollow::count() / max(BusinessProfile::count(), 1), 2),
        ];
    }

    /**
     * Estadísticas de notificaciones
     */
    private function getNotificationStats(): array
    {
        return [
            'total_sent' => Notification::count(),
            'sent_today' => Notification::whereDate('created_at', today())->count(),
            'sent_this_week' => Notification::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'by_type' => Notification::select('type', DB::raw('COUNT(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'delivery_rate' => $this->calculateDeliveryRate(),
            'active_followers' => UserFollow::count(),
        ];
    }

    /**
     * Estadísticas geográficas
     */
    private function getGeographyStats(): array
    {
        // Calcular distribución por zonas (ejemplo: por cuadrantes)
        $zones = BusinessProfile::selectRaw('
            CASE 
                WHEN latitude >= 0 AND longitude >= 0 THEN "Noreste"
                WHEN latitude >= 0 AND longitude < 0 THEN "Noroeste" 
                WHEN latitude < 0 AND longitude >= 0 THEN "Sureste"
                ELSE "Suroeste"
            END as zone,
            COUNT(*) as count
        ')
        ->groupBy(DB::raw('zone'))
        ->pluck('count', 'zone')
        ->toArray();

        return [
            'profiles_with_coordinates' => BusinessProfile::whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->count(),
            'by_zone' => $zones,
            'average_radius_coverage' => $this->calculateAverageRadiusCoverage(),
        ];
    }

    /**
     * Estadísticas de actividad
     */
    private function getActivityStats(): array
    {
        return [
            'last_24_hours' => [
                'new_users' => User::where('created_at', '>=', now()->subDay())->count(),
                'new_profiles' => BusinessProfile::where('created_at', '>=', now()->subDay())->count(),
                'notifications_sent' => Notification::where('created_at', '>=', now()->subDay())->count(),
            ],
            'peak_registration_hour' => $this->getPeakRegistrationHour(),
            'most_followed_profiles' => $this->getMostFollowedProfiles(),
        ];
    }

    /**
     * Calcular tasa de entrega de notificaciones
     */
    private function calculateDeliveryRate(): float
    {
        $total = Notification::count();
        if ($total === 0) return 100.0;

        $delivered = Notification::where('status', 'sent')->count();
        return round(($delivered / $total) * 100, 2);
    }

    /**
     * Calcular cobertura promedio de radio
     */
    private function calculateAverageRadiusCoverage(): float
    {
        // Esta es una métrica simulada - en producción sería más compleja
        return 12.5; // km promedio
    }

    /**
     * Obtener hora pico de registros
     */
    private function getPeakRegistrationHour(): int
    {
        $hourStats = User::selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderByDesc('count')
            ->first();

        return $hourStats->hour ?? 12;
    }

    /**
     * Obtener perfiles más seguidos
     */
    private function getMostFollowedProfiles(): array
    {
        return BusinessProfile::withCount('followers')
            ->orderByDesc('followers_count')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'followers_count'])
            ->toArray();
    }

    /**
     * Generar reporte de uso por período
     * Patrón: Template Method - Define estructura del reporte
     */
    public function generateUsageReport(string $period = 'month'): array
    {
        $dateRange = $this->getDateRangeForPeriod($period);
        
        return [
            'period' => $period,
            'date_range' => $dateRange,
            'metrics' => [
                'new_users' => User::whereBetween('created_at', $dateRange)->count(),
                'new_profiles' => BusinessProfile::whereBetween('created_at', $dateRange)->count(),
                'notifications_sent' => Notification::whereBetween('created_at', $dateRange)->count(),
                'new_follows' => UserFollow::whereBetween('created_at', $dateRange)->count(),
            ],
            'trends' => $this->calculateTrends($period),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Obtener rango de fechas para período
     */
    private function getDateRangeForPeriod(string $period): array
    {
        switch ($period) {
            case 'week':
                return [now()->startOfWeek(), now()->endOfWeek()];
            case 'month':
                return [now()->startOfMonth(), now()->endOfMonth()];
            case 'year':
                return [now()->startOfYear(), now()->endOfYear()];
            default:
                return [now()->startOfMonth(), now()->endOfMonth()];
        }
    }

    /**
     * Calcular tendencias
     * Patrón: Strategy Pattern - Diferentes algoritmos de cálculo de tendencias
     */
    private function calculateTrends(string $period): array
    {
        // Implementación simplificada de cálculo de tendencias
        return [
            'user_growth' => '+15%',
            'profile_growth' => '+8%',
            'engagement_rate' => '85%',
        ];
    }

    /**
     * Obtener top categorías por región
     * Patrón: Command Pattern - Encapsula consulta compleja
     */
    public function getTopCategoriesByRegion(): array
    {
        return Cache::remember('top_categories_by_region', 600, function () {
            return DB::table('business_profiles')
                ->join('business_category', 'business_profiles.id', '=', 'business_category.business_profile_id')
                ->join('categories', 'business_category.category_id', '=', 'categories.id')
                ->selectRaw('
                    categories.name as category,
                    CASE 
                        WHEN latitude >= 0 THEN "Norte"
                        ELSE "Sur"
                    END as region,
                    COUNT(*) as count
                ')
                ->groupBy('categories.name', DB::raw('region'))
                ->orderBy('count', 'desc')
                ->get()
                ->groupBy('region')
                ->map(function ($items) {
                    return $items->take(5)->pluck('count', 'category');
                })
                ->toArray();
        });
    }
} 