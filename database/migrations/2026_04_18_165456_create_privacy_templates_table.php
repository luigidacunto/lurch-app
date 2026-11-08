<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('privacy_templates', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['informativa_dati', 'foto_video']);
            $table->foreignId('comitato_regionale_id')
                  ->nullable()
                  ->constrained('comitati_regionali')
                  ->nullOnDelete();
            $table->text('testo');
            $table->string('pdf_path')->nullable();
            $table->boolean('attivo')->default(true);
            $table->timestamps();

            // Un solo template per tipo per comitato (null = globale)
            $table->unique(['tipo', 'comitato_regionale_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privacy_templates');
    }
};
