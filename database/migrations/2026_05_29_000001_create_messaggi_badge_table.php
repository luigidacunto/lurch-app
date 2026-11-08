<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaggi_badge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_evento_id')->constrained('socio_evento')->cascadeOnDelete();
            $table->text('corpo'); // HTML da Quill
            $table->timestamp('letto_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaggi_badge');
    }
};
