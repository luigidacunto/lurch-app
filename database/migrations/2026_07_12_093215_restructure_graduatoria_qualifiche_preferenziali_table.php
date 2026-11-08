<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una riga = "questo gruppo di codici corso vale questo punteggio", invece di un
 * corso per riga: molto più veloce da compilare quando più corsi condividono lo
 * stesso punteggio (caso comune, vedi regolamenti graduatorie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatoria_qualifiche_preferenziali', function (Blueprint $table) {
            $table->dropUnique(['graduatoria_id', 'catalogo_corso_id']);
            $table->dropConstrainedForeignId('catalogo_corso_id');
            $table->json('codici_corso')->after('graduatoria_id');
        });
    }

    public function down(): void
    {
        Schema::table('graduatoria_qualifiche_preferenziali', function (Blueprint $table) {
            $table->dropColumn('codici_corso');
            $table->foreignId('catalogo_corso_id')->after('graduatoria_id')->constrained('catalogo_corsi')->cascadeOnDelete();
            $table->unique(['graduatoria_id', 'catalogo_corso_id']);
        });
    }
};
