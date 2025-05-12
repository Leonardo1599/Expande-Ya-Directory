<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'     => 'required|string|max:255',
            'empresa'    => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telefono' => [
                'required',
                'regex:/^\+?[1-9]\d{1,14}$/',
            ],
            'direccion' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'nombre'     => $request->nombre,
            'apellido'   => $request->apellido,
            'empresa'    => $request->empresa,
            'email'      => $request->email,
            'telefono'   => $request->telefono,
            'direccion'  => $request->direccion,
            'password'   => Hash::make($request->password),
            'rol'        => 'cliente',
            'estado'     => 'activo',
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Se ha registrado correctamente',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 120,
        ], 201);
    }


    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = Auth::attempt($credentials)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $user = Auth::user();

        if ($user->estado !== 'activo') {
            return response()->json(['error' => 'La cuenta está ' . $user->estado], 403);
        }

        return response()->json([
            'message'      => 'Inicio de sesión exitoso',
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => JWTAuth::factory()->getTTL() * 60,
        ]);
    }

    //autenticado
    public function me()
    {
        return response()->json(Auth::user());
    }


    public function logout()
    {
        Auth::logout();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}
