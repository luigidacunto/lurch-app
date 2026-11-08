<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->boolean('richiede_attestazione_presidente')->default(false);
            $table->decimal('punteggio_attestazione_presidente', 6, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->dropColumn(['richiede_attestazione_presidente', 'punteggio_attestazione_presidente']);
        });
    }
};
