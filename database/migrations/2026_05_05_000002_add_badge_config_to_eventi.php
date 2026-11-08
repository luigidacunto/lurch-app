<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->jsonb('badge_config')->nullable()->after('invio_massivo_sollecito_staff_at');
        });
    }

    public function down(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->dropColumn('badge_config');
        });
    }
};
