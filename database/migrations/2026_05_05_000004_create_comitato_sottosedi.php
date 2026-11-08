<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comitato_sottosedi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comitato_locale_id')->constrained('comitati_locali')->cascadeOnDelete();
            $table->string('nome');
            $table->timestamps();

            $table->unique(['comitato_locale_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comitato_sottosedi');
    }
};
