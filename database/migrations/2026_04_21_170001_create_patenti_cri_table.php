<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patenti_cri', function (Blueprint $table) {
            $table->id();
            $table->string('identificativo', 20);
            $table->text('descrizione')->nullable();
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->boolean('attivo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patenti_cri');
    }
};
