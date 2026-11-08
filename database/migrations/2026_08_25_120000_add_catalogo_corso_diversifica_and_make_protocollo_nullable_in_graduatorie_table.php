<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->foreignId('catalogo_corso_diversifica_id')->nullable()
                ->after('punteggio_diversifica_comitato')
                ->constrained('catalogo_corsi')->nullOnDelete();

            $table->string('codice_protocollo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalogo_corso_diversifica_id');
            $table->string('codice_protocollo')->nullable(false)->change();
        });
    }
};
