<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de notificaciones enviadas (RF-12)
     * SOLID: Single Responsibility - Solo maneja el registro de notificaciones
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Destinatario
            $table->foreignId('business_profile_id')->constrained()->onDelete('cascade'); // Perfil relacionado
            $table->enum('type', ['email', 'sms', 'push']); // Tipo de notificación
            $table->string('subject', 255); // Asunto/título
            $table->text('message'); // Contenido del mensaje
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending'); // Estado del envío
            $table->timestamp('sent_at')->nullable(); // Cuándo se envió
            $table->json('metadata')->nullable(); // Datos adicionales (IDs externos, etc.)
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
}; 