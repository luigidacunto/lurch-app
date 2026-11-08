<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabella polimorfica: stessa logica di risposte_preregistrazione
        Schema::create('risposte_checkin', function (Blueprint $table) {
            $table->id();
            $table->string('iscrizioneable_type');
            $table->unsignedBigInteger('iscrizioneable_id');
            $table->jsonb('risposte')->default('{}');
            $table->timestamp('checkin_at');
            $table->foreignId('operatore_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['iscrizioneable_type', 'iscrizioneable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risposte_checkin');
    }
};
