<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('socio_qualifiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('soci')->cascadeOnDelete();
            $table->foreignId('qualifica_id')->constrained('qualifiche')->cascadeOnDelete();
            $table->date('data_acquisizione');
            $table->date('data_ultimo_rinnovo')->nullable();
            $table->date('scadenza_calcolata')->nullable()->index();
            $table->enum('fonte', ['importazione_excel', 'inserimento_manuale'])->default('inserimento_manuale');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['socio_id', 'qualifica_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('socio_qualifiche');
    }
};
