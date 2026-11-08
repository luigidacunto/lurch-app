<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iscrizioni', function (Blueprint $table) {
            $table->uuid('badge_token')->nullable()->unique()->after('qr_code_path');
        });

        Schema::table('iscrizioni_staff', function (Blueprint $table) {
            $table->uuid('badge_token')->nullable()->unique()->after('qr_code_path');
        });

        DB::statement("UPDATE iscrizioni SET badge_token = gen_random_uuid() WHERE badge_token IS NULL");
        DB::statement("UPDATE iscrizioni_staff SET badge_token = gen_random_uuid() WHERE badge_token IS NULL");
    }

    public function down(): void
    {
        Schema::table('iscrizioni', function (Blueprint $table) {
            $table->dropColumn('badge_token');
        });

        Schema::table('iscrizioni_staff', function (Blueprint $table) {
            $table->dropColumn('badge_token');
        });
    }
};
