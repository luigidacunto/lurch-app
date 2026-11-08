<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comitati_locali', function (Blueprint $table) {
            $table->dropColumn('sottosedi');
        });
    }

    public function down(): void
    {
        Schema::table('comitati_locali', function (Blueprint $table) {
            $table->json('sottosedi')->nullable()->after('codice');
        });
    }
};
