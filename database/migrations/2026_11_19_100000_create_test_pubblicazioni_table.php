<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pubblicazione dei risultati del test scritto al discente (punteggio e correzioni nella app), per discente.
// Tabella a parte e non colonne su test_avvii: è visibilità, non valutazione, e resta modificabile anche a esame chiuso
// mentre le tabelle delle valutazioni no. `pubblicato_il` NULL = pubblicazione ritirata.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_pubblicazioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->restrictOnDelete();
            $table->foreignId('discente_socio_id')->constrained('soci')->restrictOnDelete();
            $table->timestamp('pubblicato_il')->nullable();
            $table->foreignId('pubblicato_da')->nullable()->constrained('soci')->nullOnDelete();
            $table->timestamps();
            $table->unique(['esame_id', 'discente_socio_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('test_pubblicazioni')->exists()) {
            throw new RuntimeException('test_pubblicazioni contiene pubblicazioni: il rollback le cancellerebbe. Svuotare a mano solo dopo verifica.');
        }
        Schema::dropIfExists('test_pubblicazioni');
    }
};
