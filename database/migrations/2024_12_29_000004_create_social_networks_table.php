<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Patrón: Value Object Pattern - Las redes sociales son objetos de valor
     * SOLID: Single Responsibility - Maneja únicamente las redes sociales
     */
    public function up(): void
    {
        Schema::create('social_networks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->onDelete('cascade');
            $table->enum('platform', ['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok', 'whatsapp']);
            $table->string('url', 500); // URL de la red social
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Un perfil puede tener solo una URL por plataforma
            $table->unique(['business_profile_id', 'platform']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_networks');
    }
}; 