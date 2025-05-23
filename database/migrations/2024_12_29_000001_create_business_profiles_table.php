<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Patrón: Comando (Command Pattern) - Encapsula la operación de migración
     * SOLID: Single Responsibility - Solo se encarga de crear la tabla business_profiles
     */
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // FK al usuario propietario
            $table->string('name', 255); // Nombre del negocio
            $table->string('slug', 255)->unique(); // URL amigable
            $table->text('description')->nullable(); // Descripción de servicios
            $table->string('logo_path')->nullable(); // Ruta del logo
            $table->decimal('latitude', 10, 8); // Latitud para geolocalización
            $table->decimal('longitude', 11, 8); // Longitud para geolocalización
            $table->string('address', 500)->nullable(); // Dirección completa
            $table->string('phone', 20)->nullable(); // Teléfono de contacto
            $table->string('email', 255)->nullable(); // Email de contacto
            $table->string('website', 255)->nullable(); // Sitio web
            $table->boolean('is_active')->default(true); // Estado del perfil
            $table->timestamps();
            
            // Índices para optimizar búsquedas geográficas
            $table->index(['latitude', 'longitude']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
}; 