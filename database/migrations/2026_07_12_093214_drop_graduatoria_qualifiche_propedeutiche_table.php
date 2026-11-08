<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sostituita da una singola colonna JSON `qualifiche_propedeutiche` su `graduatorie`
 * (stesso pattern di `corsi_aggiornamento` in catalogo_corsi): più snella da compilare
 * di un Repeater con un corso per riga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('graduatoria_qualifiche_propedeutiche');
    }

    public function down(): void
    {
        Schema::create('graduatoria_qualifiche_propedeutiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('graduatoria_id')->constrained('graduatorie')->cascadeOnDelete();
            $table->foreignId('catalogo_corso_id')->constrained('catalogo_corsi')->cascadeOnDelete();

            $table->unique(['graduatoria_id', 'catalogo_corso_id']);
        });
    }
};
