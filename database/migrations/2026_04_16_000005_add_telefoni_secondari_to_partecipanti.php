<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partecipanti', function (Blueprint $table) {
            $table->json('telefoni_secondari')->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('partecipanti', function (Blueprint $table) {
            $table->dropColumn('telefoni_secondari');
        });
    }
};
