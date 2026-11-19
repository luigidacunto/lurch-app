<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pubblicazione dell'esito finale dell'esame al discente (totale, idoneità e contributo delle prove nella app), per
// discente, uno alla volta. Come test_pubblicazioni: visibilità, non valutazione, modificabile anche a esame chiuso.
// `pubblicato_il` NULL = pubblicazione ritirata.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esito_pubblicazioni', function (Blueprint $table) {
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
        if (DB::table('esito_pubblicazioni')->exists()) {
            throw new RuntimeException('esito_pubblicazioni contiene pubblicazioni: il rollback le cancellerebbe. Svuotare a mano solo dopo verifica.');
        }
        Schema::dropIfExists('esito_pubblicazioni');
    }
};
