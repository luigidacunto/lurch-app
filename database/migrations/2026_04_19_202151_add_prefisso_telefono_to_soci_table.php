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
            $table->string('prefisso_telefono', 10)->nullable()->default('+39')->after('telefono');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->dropColumn('prefisso_telefono');
        });
    }
};
