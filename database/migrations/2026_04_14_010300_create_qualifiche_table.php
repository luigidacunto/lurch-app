<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualifiche', function (Blueprint $table) {
            $table->id();
            // null = qualifica globale (superadmin)
            // !null = qualifica regionale (admin della regione)
            $table->foreignId('comitato_regionale_id')
                  ->nullable()
                  ->constrained('comitati_regionali')
                  ->cascadeOnDelete();
            $table->string('nome');
            $table->text('descrizione')->nullable();
            $table->integer('ordine')->default(0);
            $table->boolean('attiva')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualifiche');
    }
};
