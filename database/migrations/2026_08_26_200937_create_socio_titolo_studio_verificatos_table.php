<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('socio_titoli_studio_verificati', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('soci')->cascadeOnDelete();
            $table->string('tipo');
            $table->timestamp('verificato_at');
            $table->foreignId('verificato_da_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['socio_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('socio_titoli_studio_verificati');
    }
};
