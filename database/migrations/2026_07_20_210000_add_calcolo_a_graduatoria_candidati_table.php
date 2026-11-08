<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->decimal('punteggio_finale', 6, 2)->nullable()->after('note');
            $table->boolean('ammissibile')->nullable()->after('punteggio_finale');
            $table->boolean('ammesso')->nullable()->after('ammissibile');
            $table->integer('posizione_classifica')->nullable()->after('ammesso');
            $table->jsonb('dettaglio_punteggio')->nullable()->after('posizione_classifica');
            $table->timestamp('calcolato_at')->nullable()->after('dettaglio_punteggio');
        });
    }

    public function down(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->dropColumn([
                'punteggio_finale', 'ammissibile', 'ammesso',
                'posizione_classifica', 'dettaglio_punteggio', 'calcolato_at',
            ]);
        });
    }
};
