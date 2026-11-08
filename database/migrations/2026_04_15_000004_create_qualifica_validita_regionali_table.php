<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualifica_validita_regionali', function (Blueprint $table) {
            $table->id();

            $table->foreignId('qualifica_id')
                ->constrained('qualifiche')
                ->cascadeOnDelete();

            $table->foreignId('comitato_regionale_id')
                ->constrained('comitati_regionali')
                ->cascadeOnDelete();

            // Override del tipo e della durata rispetto al default nazionale
            $table->string('scadenza_tipo', 20)
                ->comment('nessuna | mesi_esatti | anno_solare');

            $table->unsignedSmallInteger('scadenza_durata')->nullable()
                ->comment('Mesi se mesi_esatti, anni se anno_solare');

            // Motivazione dell'override (es. "LR Toscana n.12/2019")
            $table->text('note')->nullable();

            $table->timestamps();

            // Una sola riga per qualifica × regione
            $table->unique(['qualifica_id', 'comitato_regionale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualifica_validita_regionali');
    }
};
