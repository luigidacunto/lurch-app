<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            // Default true: preserva il comportamento attuale (pernotto/cena sempre
            // disponibili senza vincoli) per tutti gli eventi già in produzione.
            $table->boolean('residenziale')->default(true)->after('giorni_evento');
        });
    }

    public function down(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->dropColumn('residenziale');
        });
    }
};
