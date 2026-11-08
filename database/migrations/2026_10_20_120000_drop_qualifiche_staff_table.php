<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina la tabella legacy `qualifiche_staff` (competenze staff dichiarate nel form evento,
 * meccanismo rimosso il 2026-08-22: nessun modello, Resource, form o FK la usa più).
 * Il down() ricrea solo la struttura, non i dati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('qualifiche_staff');
    }

    public function down(): void
    {
        Schema::create('qualifiche_staff', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descrizione')->nullable();
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->boolean('attivo')->default(true);
            $table->timestamps();
        });
    }
};
