<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privacy_templates', function (Blueprint $table) {
            $table->unsignedInteger('versione')->default(1)->after('testo');
        });
    }

    public function down(): void
    {
        Schema::table('privacy_templates', function (Blueprint $table) {
            $table->dropColumn('versione');
        });
    }
};
