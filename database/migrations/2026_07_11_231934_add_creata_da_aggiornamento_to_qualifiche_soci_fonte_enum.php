<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE qualifiche_soci DROP CONSTRAINT IF EXISTS qualifiche_soci_fonte_check');
        DB::statement("ALTER TABLE qualifiche_soci ADD CONSTRAINT qualifiche_soci_fonte_check CHECK (fonte IN ('importazione_excel', 'inserimento_manuale', 'calcolato', 'creata_da_aggiornamento'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE qualifiche_soci DROP CONSTRAINT IF EXISTS qualifiche_soci_fonte_check');
        DB::statement("ALTER TABLE qualifiche_soci ADD CONSTRAINT qualifiche_soci_fonte_check CHECK (fonte IN ('importazione_excel', 'inserimento_manuale', 'calcolato'))");
    }
};
