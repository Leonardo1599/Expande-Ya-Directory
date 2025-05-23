<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Patrón: Comando (Command Pattern)
     * SOLID: Single Responsibility - Maneja únicamente la creación de categorías
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique(); // Nombre de la categoría
            $table->string('slug', 100)->unique(); // URL amigable
            $table->text('description')->nullable(); // Descripción opcional
            $table->string('icon', 50)->nullable(); // Icono CSS class
            $table->string('color', 7)->default('#000000'); // Color hex
            $table->boolean('is_active')->default(true); // Estado de la categoría
            $table->timestamps();
            
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
}; 