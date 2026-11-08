<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            // Canale da cui la risposta è stata compilata: 'pubblico' (link email /form,
            // /form-staff) o 'pwa' (portale soci /app). Campo interno diagnostico, NON
            // mostrato in alcun pannello Filament.
            $table->string('fonte_compilazione', 20)->nullable()->after('compiled_at');
        });

        // Le righe esistenti precedono il tracciamento: assunte da form pubblico.
        DB::table('risposte_preregistrazione')->update(['fonte_compilazione' => 'pubblico']);
    }

    public function down(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->dropColumn('fonte_compilazione');
        });
    }
};
