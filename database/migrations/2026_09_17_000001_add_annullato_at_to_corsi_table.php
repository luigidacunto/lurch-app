<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->timestamp('annullato_at')->nullable()->after('invio_massivo_info_at');
        });
    }

    public function down(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->dropColumn('annullato_at');
        });
    }
};
