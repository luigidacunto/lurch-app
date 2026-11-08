<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('partecipante_qualifica');
    }

    public function down(): void
    {
        Schema::create('partecipante_qualifica', function (Blueprint $table) {
            $table->foreignId('partecipante_id')->constrained('soci')->cascadeOnDelete();
            $table->foreignId('qualifica_id')->constrained('qualifiche')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['partecipante_id', 'qualifica_id']);
        });
    }
};
