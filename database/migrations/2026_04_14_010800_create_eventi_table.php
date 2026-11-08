<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comitato_regionale_id')->constrained('comitati_regionali')->cascadeOnDelete();
            $table->string('nome');
            $table->text('descrizione');
            $table->date('data_inizio');
            $table->date('data_fine');
            $table->string('sede');
            $table->string('indirizzo');
            $table->string('coordinate_maps')->nullable();
            $table->jsonb('orario_campo')->default('[]');
            $table->text('privacy_informativa');
            $table->boolean('attivo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventi');
    }
};
