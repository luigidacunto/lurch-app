<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socio_evento', function (Blueprint $table) {
            $table->jsonb('badge_tokens_legacy')->nullable()->after('badge_token');
        });
    }

    public function down(): void
    {
        Schema::table('socio_evento', function (Blueprint $table) {
            $table->dropColumn('badge_tokens_legacy');
        });
    }
};
