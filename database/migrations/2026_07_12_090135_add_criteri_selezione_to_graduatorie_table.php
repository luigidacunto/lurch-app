<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->boolean('richiede_corso_sicurezza')->default(true)->after('testo_email_conferma');
            $table->boolean('preferenza_diversifica_comitato')->default(false)->after('richiede_corso_sicurezza');
            $table->boolean('selezione_a_rotazione_comitati')->default(false)->after('preferenza_diversifica_comitato');
        });
    }

    public function down(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->dropColumn(['richiede_corso_sicurezza', 'preferenza_diversifica_comitato', 'selezione_a_rotazione_comitati']);
        });
    }
};
