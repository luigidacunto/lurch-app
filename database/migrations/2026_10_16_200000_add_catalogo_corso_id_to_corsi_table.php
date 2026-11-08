<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Esami: tipo di corso (catalogo) sul corso. Additiva, nullable, nessun backfill:
// i corsi esistenti restano senza collegamento finché non lo si sceglie a mano.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->foreignId('catalogo_corso_id')->nullable()->constrained('catalogo_corsi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalogo_corso_id');
        });
    }
};
