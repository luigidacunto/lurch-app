<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comitati_locali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comitato_regionale_id')->constrained('comitati_regionali')->cascadeOnDelete();
            $table->string('nome');
            $table->string('codice')->unique();
            $table->boolean('attivo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comitati_locali');
    }
};
