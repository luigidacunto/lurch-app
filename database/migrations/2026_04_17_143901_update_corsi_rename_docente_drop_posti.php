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
        Schema::table('corsi', function (Blueprint $table) {
            $table->renameColumn('docente', 'direttore_corso');
            $table->dropColumn('posti_disponibili');
        });
    }

    public function down(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->renameColumn('direttore_corso', 'docente');
            $table->integer('posti_disponibili')->nullable()->after('docente');
        });
    }
};
