<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comitati_regionali', function (Blueprint $table) {
            $table->string('indirizzo')->nullable()->after('rappresentante_legale');
            $table->string('email')->nullable()->after('indirizzo');
        });

        Schema::table('comitati_locali', function (Blueprint $table) {
            $table->string('indirizzo')->nullable()->after('rappresentante_legale');
            $table->string('email')->nullable()->after('indirizzo');
        });
    }

    public function down(): void
    {
        Schema::table('comitati_regionali', function (Blueprint $table) {
            $table->dropColumn(['indirizzo', 'email']);
        });

        Schema::table('comitati_locali', function (Blueprint $table) {
            $table->dropColumn(['indirizzo', 'email']);
        });
    }
};
