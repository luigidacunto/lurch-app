<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partecipante_id')->constrained('partecipanti')->cascadeOnDelete();
            $table->string('email');
            $table->string('codice'); // hash bcrypt
            $table->timestamp('expires_at'); // 15 minuti
            $table->timestamp('used_at')->nullable();
            $table->integer('tentativi_falliti')->default(0);
            $table->timestamp('bloccato_fino_a')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
