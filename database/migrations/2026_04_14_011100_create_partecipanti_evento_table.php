<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partecipanti_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->foreignId('partecipante_id')->constrained('partecipanti')->cascadeOnDelete();
            $table->enum('ruolo', ['corsista', 'staff', 'operatore']);
            $table->boolean('abilitato_app')->default(true);
            $table->timestamp('created_at')->useCurrent();

            // Un partecipante può avere ruoli diversi nello stesso evento
            $table->unique(['evento_id', 'partecipante_id', 'ruolo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partecipanti_evento');
    }
};
