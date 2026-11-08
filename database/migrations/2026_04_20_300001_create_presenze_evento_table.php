<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presenze_evento', function (Blueprint $table) {
            $table->id();

            $table->foreignId('evento_id')
                  ->constrained('eventi')
                  ->cascadeOnDelete();

            $table->foreignId('partecipante_id')
                  ->constrained('soci')
                  ->cascadeOnDelete();

            $table->foreignId('iscrizione_id')
                  ->nullable()
                  ->constrained('iscrizioni')
                  ->nullOnDelete();

            $table->enum('tipo', ['pranzo', 'cena', 'pernotto', 'colazione']);
            $table->date('data');

            $table->enum('fonte', ['form', 'admin'])->default('form');
            $table->text('note')->nullable();

            $table->foreignId('updated_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->unique(['evento_id', 'partecipante_id', 'tipo', 'data']);
            $table->index(['evento_id', 'data', 'tipo']);
            $table->index(['evento_id', 'partecipante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presenze_evento');
    }
};
