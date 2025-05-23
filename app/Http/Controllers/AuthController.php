<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de autenticación (RF-1, RF-2)
 * Patrón: Controller Pattern - Maneja requests HTTP
 * SOLID: Single Responsibility - Solo maneja autenticación HTTP
 * SOLID: Dependency Injection - Inyecta AuthService
 */
class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Registro de usuario (RF-1)
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'user_type' => 'required|in:business,end_user',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->authService->register($request->all());
            
            return response()->json([
                'message' => $result['message'],
                'user' => $result['user'],
                'token' => $result['token'],
                'token_type' => 'bearer',
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error en el registro',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Autenticación de usuario (RF-2)
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->authService->login(
                $request->email, 
                $request->password
            );
            
            return response()->json([
                'message' => 'Inicio de sesión exitoso',
                'user' => $result['user'],
                'token' => $result['token'],
                'token_type' => 'bearer',
                'expires_in' => $result['expires_in'],
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Credenciales inválidas',
                'errors' => $e->errors()
            ], 401);
        }
    }

    /**
     * Verificar email (RF-1)
     */
    public function verifyEmail(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Token requerido',
                'errors' => $validator->errors()
            ], 422);
        }

        $verified = $this->authService->verifyEmail($user, $request->token);

        if ($verified) {
            return response()->json([
                'message' => 'Email verificado exitosamente'
            ]);
        }

        return response()->json([
            'message' => 'Token de verificación inválido'
        ], 400);
    }

    /**
     * Solicitar recuperación de contraseña (RF-2)
     */
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Email requerido',
                'errors' => $validator->errors()
            ], 422);
        }

        $this->authService->requestPasswordReset($request->email);

        return response()->json([
            'message' => 'Si el email existe, se enviará un enlace de recuperación'
        ]);
    }

    /**
     * Restablecer contraseña (RF-2)
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $reset = $this->authService->resetPassword(
            $request->email,
            $request->token,
            $request->password
        );

        if ($reset) {
            return response()->json([
                'message' => 'Contraseña restablecida exitosamente'
            ]);
        }

        return response()->json([
            'message' => 'Token inválido o expirado'
        ], 400);
    }

    /**
     * Obtener usuario autenticado
     */
    public function me(): JsonResponse
    {
        $user = $this->authService->getAuthenticatedUser();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        return response()->json([
            'user' => $user
        ]);
    }

    /**
     * Cerrar sesión
     */
    public function logout(): JsonResponse
    {
        $success = $this->authService->logout();

        if ($success) {
            return response()->json([
                'message' => 'Sesión cerrada exitosamente'
            ]);
        }

        return response()->json([
            'message' => 'Error al cerrar sesión'
        ], 400);
    }

    /**
     * Refrescar token JWT
     */
    public function refresh(): JsonResponse
    {
        try {
            $result = $this->authService->refresh();
            
            return response()->json([
                'token' => $result['token'],
                'token_type' => 'bearer',
                'expires_in' => $result['expires_in'],
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Token inválido',
                'errors' => $e->errors()
            ], 401);
        }
    }
}
