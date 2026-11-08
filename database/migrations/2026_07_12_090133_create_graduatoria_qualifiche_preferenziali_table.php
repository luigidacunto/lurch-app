<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduatoria_qualifiche_preferenziali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('graduatoria_id')->constrained('graduatorie')->cascadeOnDelete();
            $table->foreignId('catalogo_corso_id')->constrained('catalogo_corsi')->cascadeOnDelete();
            $table->decimal('punteggio', 6, 2);

            $table->unique(['graduatoria_id', 'catalogo_corso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduatoria_qualifiche_preferenziali');
    }
};
