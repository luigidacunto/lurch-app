<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Schede di valutazione del TCRI. Le voci si salvano per chiave di voce del modello
// (voce_codice), non per colonna: la struttura vive nello snapshot dell'esame.
// Nessun totale salvato: il calcolo è unicamente del motore.
// Tutte le FK con dati sono restrictOnDelete: una valutazione non si perde a cascata.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tcri_schede_semaforo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratorio_id')->constrained('esame_laboratori')->restrictOnDelete();
            $table->foreignId('discente_socio_id')->constrained('soci')->restrictOnDelete();
            // Un docente del corso (anche non di commissione) o il direttore, per i discenti del proprio gruppo.
            $table->foreignId('docente_socio_id')->constrained('soci')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['laboratorio_id', 'discente_socio_id', 'docente_socio_id'], 'tcri_sch_sem_lab_disc_doc_unique');
        });

        Schema::create('tcri_schede_semaforo_voci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheda_id')->constrained('tcri_schede_semaforo')->restrictOnDelete();
            $table->string('voce_codice', 100);
            $table->decimal('valore', 4, 2)->nullable(); // 0 / 0.25 / 1 dal modello; null = bozza
            $table->timestamps();

            $table->unique(['scheda_id', 'voce_codice']);
        });

        // Una sola scheda per (esame, discente, docente): le modifiche sovrascrivono. La modifica
        // spetta solo al docente autore; quando e come resta modificabile: da definire.
        Schema::create('tcri_schede_itinere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->restrictOnDelete();
            $table->foreignId('discente_socio_id')->constrained('soci')->restrictOnDelete();
            $table->foreignId('docente_socio_id')->constrained('soci')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['esame_id', 'discente_socio_id', 'docente_socio_id'], 'tcri_sch_iti_esame_disc_doc_unique');
        });

        Schema::create('tcri_schede_itinere_voci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheda_id')->constrained('tcri_schede_itinere')->restrictOnDelete();
            $table->string('voce_codice', 100);
            $table->unsignedSmallInteger('voto')->nullable(); // 1–5 dal modello; range validato dall'app
            $table->timestamps();

            $table->unique(['scheda_id', 'voce_codice']);
        });

        // Punteggio finale d'area deciso dalla commissione (scheda unica, mai sovrascrive le schede dei docenti).
        Schema::create('tcri_itinere_decisioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->restrictOnDelete();
            $table->foreignId('discente_socio_id')->constrained('soci')->restrictOnDelete();
            $table->string('area_codice', 100);
            $table->decimal('punteggio', 4, 1);
            $table->text('motivazione')->nullable();
            $table->foreignId('deciso_da')->nullable()->constrained('soci')->nullOnDelete();
            $table->timestamps();

            $table->unique(['esame_id', 'discente_socio_id', 'area_codice'], 'tcri_iti_dec_esame_disc_area_unique');
        });

        Schema::create('tcri_colloqui', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->restrictOnDelete();
            $table->foreignId('discente_socio_id')->constrained('soci')->restrictOnDelete();
            $table->unsignedSmallInteger('punteggio'); // 1–5 dal modello
            $table->foreignId('inserito_da')->nullable()->constrained('soci')->nullOnDelete();
            $table->timestamps();

            $table->unique(['esame_id', 'discente_socio_id']);
        });

        // tipo: 'commissario' = scheda individuale di un membro della commissione (direttore,
        // presidente o delegato, docente di commissione); 'finale' = scheda unica concordata
        // a voce, inserita dal direttore: l'unica che conta nel calcolo.
        Schema::create('tcri_prove_pratiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->restrictOnDelete();
            $table->foreignId('discente_socio_id')->constrained('soci')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->foreignId('membro_socio_id')->nullable()->constrained('soci')->restrictOnDelete();
            $table->foreignId('inserito_da')->nullable()->constrained('soci')->nullOnDelete();
            $table->timestamps();
        });

        // ⚠️ Con membro_socio_id NULL (finale) un UNIQUE normale non protegge: in PostgreSQL
        // NULL ≠ NULL. Servono indici univoci parziali. Nessun vincolo di completezza: le
        // schede individuali sono facoltative (il presidente/delegato può astenersi).
        DB::statement("CREATE UNIQUE INDEX tcri_prove_pratiche_finale_unique ON tcri_prove_pratiche (esame_id, discente_socio_id) WHERE tipo = 'finale'");
        DB::statement("CREATE UNIQUE INDEX tcri_prove_pratiche_commissario_unique ON tcri_prove_pratiche (esame_id, discente_socio_id, membro_socio_id) WHERE tipo = 'commissario'");
        DB::statement("ALTER TABLE tcri_prove_pratiche ADD CONSTRAINT tcri_prove_pratiche_tipo_membro_check CHECK ((tipo = 'finale' AND membro_socio_id IS NULL) OR (tipo = 'commissario' AND membro_socio_id IS NOT NULL))");

        Schema::create('tcri_prove_pratiche_voci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prova_pratica_id')->constrained('tcri_prove_pratiche')->restrictOnDelete();
            $table->string('voce_codice', 100);
            $table->decimal('valore', 4, 2)->nullable();
            $table->timestamps();

            $table->unique(['prova_pratica_id', 'voce_codice'], 'tcri_pp_voci_prova_voce_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tcri_prove_pratiche_voci');
        Schema::dropIfExists('tcri_prove_pratiche'); // porta con sé indici parziali e CHECK
        Schema::dropIfExists('tcri_colloqui');
        Schema::dropIfExists('tcri_itinere_decisioni');
        Schema::dropIfExists('tcri_schede_itinere_voci');
        Schema::dropIfExists('tcri_schede_itinere');
        Schema::dropIfExists('tcri_schede_semaforo_voci');
        Schema::dropIfExists('tcri_schede_semaforo');
    }
};
