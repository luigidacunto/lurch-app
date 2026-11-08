<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->boolean('richiede_autocertificazione')->default(false);
            $table->text('dicitura_autocertificazione')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->dropColumn(['richiede_autocertificazione', 'dicitura_autocertificazione']);
        });
    }
};
