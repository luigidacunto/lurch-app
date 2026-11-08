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
        Schema::create('socio_patenti', function (Blueprint $table) {
            $table->foreignId('socio_id')->constrained('soci')->cascadeOnDelete();
            $table->foreignId('patente_cri_id')->constrained('patenti_cri')->cascadeOnDelete();
            $table->string('fonte')->default('form_staff'); // form_staff | admin | importazione
            $table->timestamps();
            $table->primary(['socio_id', 'patente_cri_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('socio_patenti');
    }
};
