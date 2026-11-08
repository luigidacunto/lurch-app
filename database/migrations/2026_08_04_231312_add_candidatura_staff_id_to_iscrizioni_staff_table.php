<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iscrizioni_staff', function (Blueprint $table) {
            $table->foreignId('candidatura_staff_id')->nullable()->after('partecipante_id')
                ->constrained('candidature_staff')->nullOnDelete();
        });

        // Normalizza il vocabolario del ruolo: prima d'ora l'assenza di valore significava
        // "staff generico non docente", e import passati potevano usare varianti diverse per
        // indicare la docenza. Da qui in avanti il valore canonico è 'docenza'/'logistica'.
        DB::table('iscrizioni_staff')->whereNull('ruolo')->update(['ruolo' => 'logistica']);
        DB::table('iscrizioni_staff')->whereIn('ruolo', ['docenti', 'docente', 'formazione'])->update(['ruolo' => 'docenza']);
    }

    public function down(): void
    {
        Schema::table('iscrizioni_staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('candidatura_staff_id');
        });
    }
};
