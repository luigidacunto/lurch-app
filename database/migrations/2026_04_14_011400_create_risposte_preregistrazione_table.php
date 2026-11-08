<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabella polimorfica: si collega sia a iscrizioni (corsisti)
        // che a iscrizioni_staff, tramite iscrizioneable_type + iscrizioneable_id
        Schema::create('risposte_preregistrazione', function (Blueprint $table) {
            $table->id();
            $table->string('iscrizioneable_type');
            $table->unsignedBigInteger('iscrizioneable_id');
            $table->jsonb('risposte')->default('{}');
            $table->boolean('privacy_accettata')->default(false);
            $table->boolean('foto_video_accettata')->default(false);
            $table->timestamp('compiled_at');
            $table->timestamps();

            $table->index(['iscrizioneable_type', 'iscrizioneable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risposte_preregistrazione');
    }
};
