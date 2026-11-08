<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->boolean('consenso_salute_accettata')->default(false)->after('privacy_accettata');
        });
    }

    public function down(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->dropColumn('consenso_salute_accettata');
        });
    }
};
