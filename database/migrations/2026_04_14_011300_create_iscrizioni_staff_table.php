<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iscrizioni_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->foreignId('partecipante_id')->constrained('partecipanti')->cascadeOnDelete();
            // Token UUID per il form di preregistrazione staff
            $table->uuid('token')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->jsonb('giorni_disponibili')->default('[]');
            $table->enum('stato', ['pending', 'form_completato', 'checkin_fatto'])->default('pending');
            $table->string('qr_code_path')->nullable();
            $table->timestamps();

            $table->unique(['evento_id', 'partecipante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iscrizioni_staff');
    }
};
