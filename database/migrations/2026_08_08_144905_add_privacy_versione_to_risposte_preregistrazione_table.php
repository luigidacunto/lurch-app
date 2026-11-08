<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->unsignedInteger('privacy_versione_accettata')->nullable()->after('foto_video_accettata');
        });

        DB::table('risposte_preregistrazione')->whereNotNull('privacy_accettata')->update(['privacy_versione_accettata' => 1]);
    }

    public function down(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->dropColumn('privacy_versione_accettata');
        });
    }
};
