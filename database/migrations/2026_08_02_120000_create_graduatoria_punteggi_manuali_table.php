<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduatoria_punteggi_manuali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('graduatoria_candidato_id')->constrained('graduatoria_candidati')->cascadeOnDelete();
            $table->decimal('valore', 6, 2);
            $table->text('motivo');
            $table->foreignId('creato_da_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduatoria_punteggi_manuali');
    }
};
