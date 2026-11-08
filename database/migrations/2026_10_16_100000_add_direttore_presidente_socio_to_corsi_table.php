<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Esami TCRI: direttore e presidente/delegato collegati a un socio.
// Solo additiva: `direttore_corso` (testo) resta, nessun backfill dei corsi esistenti.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->foreignId('direttore_socio_id')->nullable()->constrained('soci')->nullOnDelete();
            $table->foreignId('presidente_socio_id')->nullable()->constrained('soci')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('direttore_socio_id');
            $table->dropConstrainedForeignId('presidente_socio_id');
        });
    }
};
