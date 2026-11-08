<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->string('email_in_verifica')->nullable();
            $table->timestamp('email_verificata_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->dropColumn(['email_in_verifica', 'email_verificata_at']);
        });
    }
};
