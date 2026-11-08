<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            // NULL = socio attivo. Valorizzata = non più associato come volontario dal giorno X
            // (riconciliazione da import GAIA o impostazione manuale). Non cancella il socio:
            // lo nasconde solo da lista Soci e ricerca Qualifiche.
            $table->date('dimesso_il')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->dropColumn('dimesso_il');
        });
    }
};
