<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla pivot para relación Many-to-Many entre business_profiles y categories
     * SOLID: Single Responsibility - Solo maneja la relación entre entidades
     */
    public function up(): void
    {
        Schema::create('business_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Evitar duplicados en la relación
            $table->unique(['business_profile_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_category');
    }
}; 