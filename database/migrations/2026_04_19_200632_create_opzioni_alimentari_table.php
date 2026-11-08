<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('opzioni_alimentari', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // 'allergene' | 'dieta'
            $table->string('nome', 100);
            $table->string('etichetta', 255);
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->boolean('attivo')->default(true);
            $table->timestamps();

            $table->index(['tipo', 'attivo', 'ordine']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opzioni_alimentari');
    }
};
