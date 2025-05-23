<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Actualiza la tabla users para soportar tipos de usuario (RF-1)
     * SOLID: Single Responsibility - Solo modifica la estructura de usuarios
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('user_type', ['end_user', 'business'])->default('end_user')->after('email');
            $table->string('phone', 20)->nullable()->after('email');
            $table->decimal('latitude', 10, 8)->nullable()->after('phone'); // Para búsquedas cercanas
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->boolean('is_active')->default(true)->after('remember_token');
            
            $table->index(['latitude', 'longitude']);
            $table->index('user_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropIndex(['user_type']);
            $table->dropColumn(['user_type', 'phone', 'latitude', 'longitude', 'is_active']);
        });
    }
}; 