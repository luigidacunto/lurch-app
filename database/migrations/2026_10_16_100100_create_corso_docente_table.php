<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Esami TCRI: docenti del corso (0..N), scelti tra tutti i soci.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corso_docente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corso_id')->constrained('corsi')->cascadeOnDelete();
            $table->foreignId('socio_id')->constrained('soci')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['corso_id', 'socio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corso_docente');
    }
};
