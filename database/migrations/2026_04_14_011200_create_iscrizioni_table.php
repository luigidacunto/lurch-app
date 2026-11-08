<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iscrizioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corso_id')->constrained('corsi')->cascadeOnDelete();
            $table->foreignId('partecipante_id')->constrained('partecipanti')->cascadeOnDelete();
            // Token UUID generato da PostgreSQL — usato come link univoco per il form
            $table->uuid('token')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->enum('stato', ['pending', 'form_completato', 'checkin_fatto'])->default('pending');
            $table->string('qr_code_path')->nullable();
            $table->timestamps();

            $table->unique(['corso_id', 'partecipante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iscrizioni');
    }
};
