<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE partecipanti_evento RENAME TO socio_evento');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE socio_evento RENAME TO partecipanti_evento');
    }
};
