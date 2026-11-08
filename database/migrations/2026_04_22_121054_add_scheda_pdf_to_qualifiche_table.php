<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('qualifiche', function (Blueprint $table) {
            $table->string('scheda_pdf')->nullable()->after('corsi_aggiornamento');
        });
    }

    public function down(): void
    {
        Schema::table('qualifiche', function (Blueprint $table) {
            $table->dropColumn('scheda_pdf');
        });
    }
};
