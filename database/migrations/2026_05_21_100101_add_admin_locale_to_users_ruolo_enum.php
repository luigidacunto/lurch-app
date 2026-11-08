<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL non supporta ALTER COLUMN su enum: drop del check constraint e ricreazione
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_ruolo_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_ruolo_check CHECK (ruolo IN ('superadmin', 'admin', 'admin_locale', 'operatore'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_ruolo_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_ruolo_check CHECK (ruolo IN ('superadmin', 'admin', 'operatore'))");
    }
};
