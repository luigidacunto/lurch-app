<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iscrizioni', function (Blueprint $table) {
            $table->timestamp('form_inviato_at')->nullable()->after('stato');
        });
    }

    public function down(): void
    {
        Schema::table('iscrizioni', function (Blueprint $table) {
            $table->dropColumn('form_inviato_at');
        });
    }
};
