<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\SocialNetwork;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Servicio para gestión de redes sociales (RF-9)
 * Patrón: Strategy Pattern - Diferentes estrategias de validación por plataforma
 * Patrón: Factory Method - Crea validadores específicos por plataforma
 * SOLID: Single Responsibility - Solo maneja redes sociales
 * SOLID: Open/Closed - Abierto para nuevas plataformas, cerrado para modificación
 */
class SocialNetworkService
{
    // Factory Method - Crea validadores específicos
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            'facebook' => new FacebookUrlValidator(),
            'instagram' => new InstagramUrlValidator(),
            'twitter' => new TwitterUrlValidator(),
            'linkedin' => new LinkedInUrlValidator(),
            'youtube' => new YouTubeUrlValidator(),
            'tiktok' => new TikTokUrlValidator(),
            'whatsapp' => new WhatsAppUrlValidator(),
        ];
    }

    /**
     * Agregar o actualizar red social de un perfil (RF-9)
     * Patrón: Command Pattern - Encapsula la operación
     */
    public function attachSocialNetwork(
        BusinessProfile $profile, 
        string $platform, 
        string $url
    ): SocialNetwork {
        return DB::transaction(function () use ($profile, $platform, $url) {
            // Validar URL según la plataforma
            $this->validatePlatformUrl($platform, $url);

            // Buscar si ya existe la red social
            $socialNetwork = $profile->socialNetworks()
                ->where('platform', $platform)
                ->first();

            if ($socialNetwork) {
                // Actualizar URL existente
                $socialNetwork->update(['url' => $url, 'is_active' => true]);
            } else {
                // Crear nueva red social
                $socialNetwork = $profile->socialNetworks()->create([
                    'platform' => $platform,
                    'url' => $url,
                    'is_active' => true,
                ]);
            }

            return $socialNetwork;
        });
    }

    /**
     * Validar URL según plataforma (RF-9)
     * Patrón: Strategy Pattern - Usa estrategia específica por plataforma
     */
    public function validatePlatformUrl(string $platform, string $url): bool
    {
        $validator = $this->validators[$platform] ?? new GenericUrlValidator();
        
        if (!$validator->isValid($url)) {
            throw new \InvalidArgumentException(
                "URL no válida para la plataforma {$platform}: {$url}"
            );
        }

        return true;
    }

    /**
     * Verificar si URLs están activas/accesibles (RF-9)
     * Patrón: Chain of Responsibility - Verifica en cadena
     */
    public function verifyUrls(BusinessProfile $profile): array
    {
        $results = [];
        
        foreach ($profile->socialNetworks as $socialNetwork) {
            $isAccessible = $this->checkUrlAccessibility($socialNetwork->url);
            
            $results[] = [
                'platform' => $socialNetwork->platform,
                'url' => $socialNetwork->url,
                'is_accessible' => $isAccessible,
                'last_checked' => now(),
            ];

            // Actualizar estado si no es accesible
            if (!$isAccessible) {
                $socialNetwork->update(['is_active' => false]);
            }
        }

        return $results;
    }

    /**
     * Verificar accesibilidad de URL
     */
    private function checkUrlAccessibility(string $url): bool
    {
        try {
            $response = Http::timeout(10)->head($url);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Eliminar red social
     */
    public function removeSocialNetwork(BusinessProfile $profile, string $platform): bool
    {
        return $profile->socialNetworks()
            ->where('platform', $platform)
            ->delete() > 0;
    }

    /**
     * Activar/desactivar red social
     * Patrón: State Pattern - Cambio de estado
     */
    public function toggleSocialNetwork(SocialNetwork $socialNetwork): SocialNetwork
    {
        $socialNetwork->update(['is_active' => !$socialNetwork->is_active]);
        return $socialNetwork->fresh();
    }

    /**
     * Obtener plataformas soportadas
     */
    public function getSupportedPlatforms(): array
    {
        return array_keys($this->validators);
    }
}

/**
 * Interfaz para validadores de URL
 * SOLID: Interface Segregation - Interfaz específica para validación
 */
interface UrlValidatorInterface
{
    public function isValid(string $url): bool;
}

/**
 * Validador genérico de URLs
 */
class GenericUrlValidator implements UrlValidatorInterface
{
    public function isValid(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

/**
 * Validadores específicos por plataforma (Strategy Pattern)
 */
class FacebookUrlValidator implements UrlValidatorInterface
{
    public function isValid(string $url): bool
    {
        return preg_match('/^https?:\/\/(www\.)?facebook\.com\/[\w\.\-]+\/?$/i', $url) === 1;
    }
}

class InstagramUrlValidator implements UrlValidatorInterface
{
    public function isValid(string $url): bool
    {
        return preg_match('/^https?:\/\/(www\.)?instagram\.com\/[\w\.\-]+\/?$/i', $url) === 1;
    }
}

class TwitterUrlValidator implements UrlValidatorInterface
{
    public function isValid(string $url): bool
    {
        return preg_match('/^https?:\/\/(www\.)?(twitter\.com|x\.com)\/[\w\.\-]+\/?$/i', $url) === 1;
    }
}

class LinkedInUrlValidator implements UrlValidatorInterface
{
    public function isValid(string $url): bool
    {
        return preg_match('/^https?:\/\/(www\.)?linkedin\.com\/(in|company)\/[\w\.\-]+\/?$/i', $url) === 1;
    }
}

class YouTubeUrlValidator implements UrlValidatorInterface
{
    public function isValid(string $url): bool
    {
        return preg_match('/^https?:\/\/(www\.)?youtube\.com\/(channel\/|c\/|user\/)?[\w\.\-]+\/?$/i', $url) === 1;
    }
}

class TikTokUrlValidator implements UrlValidatorInterface
{
    public function isValid(string $url): bool
    {
        return preg_match('/^https?:\/\/(www\.)?tiktok\.com\/@[\w\.\-]+\/?$/i', $url) === 1;
    }
}

class WhatsAppUrlValidator implements UrlValidatorInterface
{
    public function isValid(string $url): bool
    {
        return preg_match('/^https?:\/\/(wa\.me|api\.whatsapp\.com)\/[\d\+]+(\?.*)?$/i', $url) === 1;
    }
} 