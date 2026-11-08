<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ospiti_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->string('nome');
            $table->string('cognome');
            $table->string('cf', 16)->nullable();
            $table->timestamps();

            // stesso CF non registrabile due volte per evento (quando presente)
            $table->unique(['evento_id', 'cf']);
        });

        Schema::create('accessi_evento', function (Blueprint $table) {
            $table->id();
            $table->morphs('iscrizioneable'); // iscrizioneable_type + iscrizioneable_id + indice
            $table->enum('tipo', ['entrata', 'uscita']);
            $table->dateTime('timestamp_accesso');
            $table->foreignId('operatore_id')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['iscrizioneable_type', 'iscrizioneable_id', 'timestamp_accesso'], 'accessi_iscrizioneable_ts_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessi_evento');
        Schema::dropIfExists('ospiti_evento');
    }
};
