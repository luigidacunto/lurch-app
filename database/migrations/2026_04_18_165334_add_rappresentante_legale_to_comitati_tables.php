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
        Schema::table('comitati_regionali', function (Blueprint $table) {
            $table->string('rappresentante_legale')->nullable()->after('nome');
        });
        Schema::table('comitati_locali', function (Blueprint $table) {
            $table->string('rappresentante_legale')->nullable()->after('nome');
        });
    }

    public function down(): void
    {
        Schema::table('comitati_regionali', function (Blueprint $table) {
            $table->dropColumn('rappresentante_legale');
        });
        Schema::table('comitati_locali', function (Blueprint $table) {
            $table->dropColumn('rappresentante_legale');
        });
    }
};
