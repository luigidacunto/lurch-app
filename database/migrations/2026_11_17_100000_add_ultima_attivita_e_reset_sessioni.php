<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ultima attività (pannello per gli utenti, PWA per i soci) e reset delle sessioni dei soci.
 * Colonne nullable: null = mai visto dopo il rilascio / nessun reset eseguito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable();
        });

        Schema::table('soci', function (Blueprint $table) {
            $table->timestamp('ultima_attivita_app')->nullable();
            // Sessioni e cookie "ricordami" della PWA emessi prima di questo istante non sono più validi.
            $table->timestamp('sessioni_valide_dal')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_activity_at');
        });

        Schema::table('soci', function (Blueprint $table) {
            $table->dropColumn(['ultima_attivita_app', 'sessioni_valide_dal']);
        });
    }
};
