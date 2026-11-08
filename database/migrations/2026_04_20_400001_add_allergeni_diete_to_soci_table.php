<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->jsonb('allergeni')->nullable()->after('note_alimentari');
            $table->jsonb('diete')->nullable()->after('allergeni');
        });
    }

    public function down(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->dropColumn(['allergeni', 'diete']);
        });
    }
};
