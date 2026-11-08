<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->string('titolo');
            $table->jsonb('domande')->default('[]');
            $table->boolean('attivo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionari');
    }
};
