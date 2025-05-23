<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sistema de seguimiento de perfiles para notificaciones
     * SOLID: Single Responsibility - Maneja únicamente las relaciones de seguimiento
     */
    public function up(): void
    {
        Schema::create('user_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Quien sigue
            $table->foreignId('business_profile_id')->constrained()->onDelete('cascade'); // A quien sigue
            $table->boolean('email_notifications')->default(true); // Notificaciones por email
            $table->boolean('sms_notifications')->default(false); // Notificaciones por SMS
            $table->boolean('push_notifications')->default(true); // Notificaciones push
            $table->timestamps();
            
            // Un usuario no puede seguir el mismo perfil dos veces
            $table->unique(['user_id', 'business_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_follows');
    }
}; 