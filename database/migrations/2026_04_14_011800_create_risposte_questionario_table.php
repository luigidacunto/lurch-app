<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nessuna FK al partecipante — le risposte sono completamente anonime
        Schema::create('risposte_questionario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionario_id')->constrained('questionari')->cascadeOnDelete();
            $table->uuid('token_risposta')->unique();
            $table->jsonb('risposte')->default('{}');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risposte_questionario');
    }
};
