<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partecipanti', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cognome');
            $table->string('codice_fiscale')->unique();
            $table->string('email')->unique();
            $table->string('telefono')->nullable();
            $table->foreignId('comitato_regionale_id')
                  ->nullable()
                  ->constrained('comitati_regionali')
                  ->nullOnDelete();
            $table->foreignId('comitato_locale_id')
                  ->nullable()
                  ->constrained('comitati_locali')
                  ->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partecipanti');
    }
};
