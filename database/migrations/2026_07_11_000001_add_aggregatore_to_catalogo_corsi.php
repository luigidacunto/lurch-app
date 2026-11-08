<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_corsi', function (Blueprint $table) {
            $table->boolean('aggregatore')->default(false)->after('equivalente_a_id');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_corsi', function (Blueprint $table) {
            $table->dropColumn('aggregatore');
        });
    }
};
