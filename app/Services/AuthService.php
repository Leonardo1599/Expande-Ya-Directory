<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de autenticación y registro (RF-1, RF-2)
 * Patrón: Facade Pattern - Simplifica la interfaz de autenticación
 * Patrón: Factory Method - Crea diferentes tipos de usuarios
 * SOLID: Single Responsibility - Solo maneja autenticación
 * SOLID: Dependency Inversion - Depende de abstracciones
 */
class AuthService
{
    /**
     * Registrar nuevo usuario (RF-1)
     * Patrón: Factory Method - Crea usuario según tipo
     */
    public function register(array $userData): array
    {
        return DB::transaction(function () use ($userData) {
            // Validar tipo de usuario
            $this->validateUserType($userData['user_type']);

            // Crear usuario
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'user_type' => $userData['user_type'],
                'phone' => $userData['phone'] ?? null,
                'latitude' => $userData['latitude'] ?? null,
                'longitude' => $userData['longitude'] ?? null,
                'is_active' => false, // Inactivo hasta verificar email
            ]);

            // Enviar email de verificación
            $this->sendVerificationEmail($user);

            // Generar token JWT
            $token = JWTAuth::fromUser($user);

            return [
                'user' => $user,
                'token' => $token,
                'message' => 'Usuario registrado. Verifique su email para activar la cuenta.',
            ];
        });
    }

    /**
     * Autenticación de usuario (RF-2)
     * Patrón: Strategy Pattern - Diferentes estrategias de autenticación
     */
    public function login(string $email, string $password): array
    {
        // Validar credenciales
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no coinciden con nuestros registros.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['La cuenta no está activada. Verifique su email.'],
            ]);
        }

        // Generar token JWT
        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];
    }

    /**
     * Cerrar sesión
     */
    public function logout(): bool
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Refrescar token JWT
     */
    public function refresh(): array
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            
            return [
                'token' => $newToken,
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ];
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'token' => ['Token inválido o expirado'],
            ]);
        }
    }

    /**
     * Verificar email del usuario
     */
    public function verifyEmail(User $user, string $token): bool
    {
        // Validar token de verificación
        $expectedToken = $this->generateVerificationToken($user);
        
        if (!hash_equals($expectedToken, $token)) {
            return false;
        }

        // Activar usuario
        $user->update([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return true;
    }

    /**
     * Solicitar recuperación de contraseña (RF-2)
     * Patrón: Command Pattern - Encapsula la solicitud de recuperación
     */
    public function requestPasswordReset(string $email): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return false; // No revelamos si el email existe por seguridad
        }

        // Generar token temporal
        $token = Str::random(60);
        
        // Guardar token en cache (expires en 1 hora)
        cache()->put("password_reset.{$user->id}", $token, 3600);

        // Enviar email con enlace de recuperación
        $this->sendPasswordResetEmail($user, $token);

        return true;
    }

    /**
     * Restablecer contraseña con token
     */
    public function resetPassword(string $email, string $token, string $newPassword): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return false;
        }

        // Validar token
        $cachedToken = cache()->get("password_reset.{$user->id}");
        
        if (!$cachedToken || !hash_equals($cachedToken, $token)) {
            return false;
        }

        // Actualizar contraseña
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Eliminar token usado
        cache()->forget("password_reset.{$user->id}");

        return true;
    }

    /**
     * Obtener usuario autenticado
     */
    public function getAuthenticatedUser(): ?User
    {
        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validar tipo de usuario permitido
     * SOLID: Single Responsibility - Solo valida tipos
     */
    private function validateUserType(string $userType): void
    {
        $allowedTypes = ['business', 'end_user'];
        
        if (!in_array($userType, $allowedTypes)) {
            throw new \InvalidArgumentException(
                "Tipo de usuario no válido: {$userType}. Tipos permitidos: " . 
                implode(', ', $allowedTypes)
            );
        }
    }

    /**
     * Enviar email de verificación
     */
    private function sendVerificationEmail(User $user): void
    {
        $token = $this->generateVerificationToken($user);
        
        // Aquí enviarías el email real con Mail::send()
        // Por simplicidad, solo lo logueamos
        \Log::info("Email de verificación para {$user->email}: token={$token}");
    }

    /**
     * Generar token de verificación
     */
    private function generateVerificationToken(User $user): string
    {
        return hash_hmac('sha256', $user->email . $user->created_at, config('app.key'));
    }

    /**
     * Enviar email de recuperación de contraseña
     */
    private function sendPasswordResetEmail(User $user, string $token): void
    {
        // Aquí enviarías el email real con Mail::send()
        // Por simplicidad, solo lo logueamos
        \Log::info("Email de recuperación para {$user->email}: token={$token}");
    }
} 