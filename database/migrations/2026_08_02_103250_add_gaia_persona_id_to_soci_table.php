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
        Schema::table('soci', function (Blueprint $table) {
            $table->string('gaia_persona_id')->nullable()->after('codice_fiscale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->dropColumn('gaia_persona_id');
        });
    }
};
