<?php

/*use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SocialNetworkController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Directorio Web de Servicios
|--------------------------------------------------------------------------
| Rutas para implementar todos los Requisitos Funcionales (RF-1 a RF-12)
| Patrón: RESTful API - Siguiendo convenciones REST
| SOLID: Interface Segregation - Rutas específicas por funcionalidad
*/

// Rutas públicas de autenticación (RF-1, RF-2)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-email/{user}', [AuthController::class, 'verifyEmail']);
    Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Rutas públicas de perfiles (RF-5, RF-6, RF-7, RF-8)
Route::prefix('profiles')->group(function () {
    // Búsqueda y listado público
    Route::get('/', [BusinessProfileController::class, 'index']);
    Route::get('/nearby', [BusinessProfileController::class, 'nearby']);
    Route::get('/{slug}', [BusinessProfileController::class, 'show']);
    
    // Redes sociales públicas
    Route::get('/{profile}/social-networks', [SocialNetworkController::class, 'getProfileSocialNetworks']);
});

// Rutas públicas de mapas interactivos (RF-7, RF-8)
Route::prefix('map')->group(function () {
    Route::get('/markers', [MapController::class, 'getMarkers']);
    Route::get('/config', [MapController::class, 'getConfig']);
    Route::get('/script', [MapController::class, 'getMapScript']);
});

// Rutas públicas de utilidades
Route::prefix('utils')->group(function () {
    Route::get('/social-platforms', [SocialNetworkController::class, 'getSupportedPlatforms']);
    Route::post('/validate-social-url', [SocialNetworkController::class, 'validateUrl']);
});

// Rutas protegidas - requieren autenticación
Route::middleware(['auth:api'])->group(function () {
    
    // Gestión de autenticación autenticada
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Gestión de perfiles de negocio (RF-3, RF-4) - Solo usuarios business
    Route::prefix('profiles')->middleware('business.user')->group(function () {
        Route::post('/', [BusinessProfileController::class, 'store']);
        Route::put('/{profile}', [BusinessProfileController::class, 'update']);
        Route::delete('/{profile}', [BusinessProfileController::class, 'destroy']);
        Route::post('/{profile}/logo', [BusinessProfileController::class, 'uploadLogo']);
        Route::patch('/{profile}/toggle-status', [BusinessProfileController::class, 'toggleStatus']);
    });

    // Gestión de redes sociales (RF-9) - Solo propietarios
    Route::prefix('profiles/{profile}/social-networks')->group(function () {
        Route::post('/', [SocialNetworkController::class, 'attachSocialNetwork']);
        Route::post('/verify', [SocialNetworkController::class, 'verifyUrls']);
        Route::delete('/{platform}', [SocialNetworkController::class, 'removeSocialNetwork']);
    });

    Route::prefix('social-networks')->group(function () {
        Route::patch('/{socialNetwork}/toggle', [SocialNetworkController::class, 'toggleSocialNetwork']);
    });

    // Seguimiento de perfiles y notificaciones (RF-10, RF-11, RF-12) - Solo usuarios finales
    Route::prefix('follow')->middleware('end.user')->group(function () {
        Route::post('/profiles/{profile}', [NotificationController::class, 'followProfile']);
        Route::delete('/profiles/{profile}', [NotificationController::class, 'unfollowProfile']);
        Route::get('/profiles/{profile}/status', [NotificationController::class, 'checkFollowStatus']);
        Route::put('/profiles/{profile}/notifications', [NotificationController::class, 'updateNotificationPreferences']);
        Route::get('/profiles/{profile}/notifications', [NotificationController::class, 'getNotificationPreferences']);
    });

    // Gestión de notificaciones (RF-12)
    Route::prefix('notifications')->group(function () {
        Route::get('/history', [NotificationController::class, 'getNotificationHistory']);
        Route::put('/global-preferences', [NotificationController::class, 'updateGlobalNotificationPreferences']);
    });

    // Perfiles seguidos por el usuario
    Route::prefix('my')->middleware('end.user')->group(function () {
        Route::get('/followed-profiles', [NotificationController::class, 'getFollowedProfiles']);
        Route::get('/follow-stats', [NotificationController::class, 'getUserFollowStats']);
    });
});
