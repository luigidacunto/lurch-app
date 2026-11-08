<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->dropUnique('partecipanti_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->unique('email', 'partecipanti_email_unique');
        });
    }
};
