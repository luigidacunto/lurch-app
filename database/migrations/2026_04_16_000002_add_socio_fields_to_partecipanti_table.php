<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partecipanti', function (Blueprint $table) {
            $table->date('data_nascita')->nullable()->after('email');
            $table->date('volontario_da')->nullable()->after('data_nascita');

            // Estensione (facoltativa, max 1 per socio)
            $table->foreignId('comitato_locale_estensione_id')
                ->nullable()
                ->after('comitato_locale_id')
                ->constrained('comitati_locali')
                ->nullOnDelete();

            $table->foreignId('comitato_regionale_estensione_id')
                ->nullable()
                ->after('comitato_locale_estensione_id')
                ->constrained('comitati_regionali')
                ->nullOnDelete();

            // Salute
            $table->text('allergie')->nullable()->after('comitato_regionale_estensione_id');
            $table->text('note_alimentari')->nullable()->after('allergie')
                ->comment('Intolleranze e allergie alimentari');

            // Soft delete
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('partecipanti', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['data_nascita', 'volontario_da', 'allergie', 'note_alimentari']);
            $table->dropConstrainedForeignId('comitato_locale_estensione_id');
            $table->dropConstrainedForeignId('comitato_regionale_estensione_id');
        });
    }
};
