<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL non supporta ALTER COLUMN su enum: drop del check constraint e ricreazione
        DB::statement('ALTER TABLE iscrizioni DROP CONSTRAINT IF EXISTS iscrizioni_stato_check');
        DB::statement("ALTER TABLE iscrizioni ADD CONSTRAINT iscrizioni_stato_check CHECK (stato IN ('pending','form_completato','checkin_fatto','ritirato'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE iscrizioni DROP CONSTRAINT IF EXISTS iscrizioni_stato_check');
        DB::statement("ALTER TABLE iscrizioni ADD CONSTRAINT iscrizioni_stato_check CHECK (stato IN ('pending','form_completato','checkin_fatto'))");
    }
};
