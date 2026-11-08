<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidatura_staff_corso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidatura_staff_id')->constrained('candidature_staff')->cascadeOnDelete();
            $table->foreignId('corso_id')->constrained('corsi')->cascadeOnDelete();

            $table->unique(['candidatura_staff_id', 'corso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidatura_staff_corso');
    }
};
