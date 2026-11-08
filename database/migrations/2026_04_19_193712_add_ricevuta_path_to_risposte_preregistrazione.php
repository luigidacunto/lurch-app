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
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->string('ricevuta_path')->nullable()->after('foto_video_accettata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->dropColumn('ricevuta_path');
        });
    }
};
