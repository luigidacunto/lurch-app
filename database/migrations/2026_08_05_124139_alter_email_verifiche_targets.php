<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fino ad oggi email_verifiche ha come unico target possibile un Socio (socio_id
 * NOT NULL). Ora la verifica email può riguardare anche una candidatura senza
 * socio agganciato (graduatoria o staff, es. CF non ancora trovato o docente
 * esterno all'associazione) — socio_id diventa nullable (nessun impatto sulle
 * righe esistenti, tutte già valorizzate) e si aggiungono le due FK alternative.
 * Esattamente una delle tre deve essere valorizzata (CHECK), mai zero o più di una.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE email_verifiche ALTER COLUMN socio_id DROP NOT NULL');

        Schema::table('email_verifiche', function (Blueprint $table) {
            $table->foreignId('candidatura_staff_id')->nullable()->after('socio_id')->constrained('candidature_staff')->cascadeOnDelete();
            $table->foreignId('graduatoria_candidato_id')->nullable()->after('candidatura_staff_id')->constrained('graduatoria_candidati')->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE email_verifiche ADD CONSTRAINT email_verifiche_un_solo_target_check CHECK (
                (
                    (socio_id IS NOT NULL)::int
                    + (candidatura_staff_id IS NOT NULL)::int
                    + (graduatoria_candidato_id IS NOT NULL)::int
                ) = 1
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE email_verifiche DROP CONSTRAINT email_verifiche_un_solo_target_check');

        Schema::table('email_verifiche', function (Blueprint $table) {
            $table->dropConstrainedForeignId('graduatoria_candidato_id');
            $table->dropConstrainedForeignId('candidatura_staff_id');
        });

        DB::statement('ALTER TABLE email_verifiche ALTER COLUMN socio_id SET NOT NULL');
    }
};
