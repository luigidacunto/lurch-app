<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conflitti_iscrizioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partecipante_id')->constrained('soci')->cascadeOnDelete();
            $table->foreignId('iscrizione_a_id')->constrained('iscrizioni')->cascadeOnDelete();
            $table->foreignId('iscrizione_b_id')->constrained('iscrizioni')->cascadeOnDelete();
            $table->foreignId('corso_a_id')->constrained('corsi')->cascadeOnDelete();
            $table->foreignId('corso_b_id')->constrained('corsi')->cascadeOnDelete();
            $table->timestamps();

            // Evita duplicati — la coppia (a,b) è sempre ordinata con a < b
            $table->unique(['iscrizione_a_id', 'iscrizione_b_id']);

            $table->index('partecipante_id');
            $table->index('iscrizione_a_id');
            $table->index('iscrizione_b_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflitti_iscrizioni');
    }
};
