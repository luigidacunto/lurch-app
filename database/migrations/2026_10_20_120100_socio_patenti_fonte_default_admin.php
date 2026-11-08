<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `socio_patenti.fonte` aveva default 'form_staff' (form staff rimosso il 2026-08-22). L'unico
 * scrittore senza fonte esplicita è l'editor manuale in SocioResource (CheckboxList): da ora le
 * patenti inserite a mano risultano 'admin'. Solo il default cambia, nessuna riga esistente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE socio_patenti ALTER COLUMN fonte SET DEFAULT 'admin'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE socio_patenti ALTER COLUMN fonte SET DEFAULT 'form_staff'");
    }
};
