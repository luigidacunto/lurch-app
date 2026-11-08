<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Utenti: admin_locale ha un comitato locale specifico
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('comitato_locale_id')
                ->nullable()
                ->after('comitato_regionale_id')
                ->constrained('comitati_locali')
                ->nullOnDelete();
        });

        // Eventi: null = regionale, valorizzato = evento locale
        Schema::table('eventi', function (Blueprint $table) {
            $table->foreignId('comitato_locale_id')
                ->nullable()
                ->after('comitato_regionale_id')
                ->constrained('comitati_locali')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comitato_locale_id');
        });

        Schema::table('eventi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comitato_locale_id');
        });
    }
};
