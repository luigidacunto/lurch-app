<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partecipanti', function (Blueprint $table) {
            $table->string('ruolo_associativo', 50)->nullable()->after('volontario_da');
        });
    }

    public function down(): void
    {
        Schema::table('partecipanti', function (Blueprint $table) {
            $table->dropColumn('ruolo_associativo');
        });
    }
};
