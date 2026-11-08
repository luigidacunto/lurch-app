<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->boolean('rotazione_multi_turno')->default(false)->after('selezione_a_rotazione_comitati');
        });
    }

    public function down(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->dropColumn('rotazione_multi_turno');
        });
    }
};
