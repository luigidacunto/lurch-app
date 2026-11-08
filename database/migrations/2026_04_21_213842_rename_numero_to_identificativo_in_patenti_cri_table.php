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
        if (Schema::hasColumn('patenti_cri', 'numero')) {
            Schema::table('patenti_cri', function (Blueprint $table) {
                $table->renameColumn('numero', 'identificativo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('patenti_cri', 'identificativo')) {
            Schema::table('patenti_cri', function (Blueprint $table) {
                $table->renameColumn('identificativo', 'numero');
            });
        }
    }
};
