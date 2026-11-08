<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabella polimorfica: traccia gli invii email per iscrizioni e iscrizioni_staff
        Schema::create('email_invii', function (Blueprint $table) {
            $table->id();
            $table->string('iscrizioneable_type');
            $table->unsignedBigInteger('iscrizioneable_id');
            $table->enum('tipo', ['otp', 'preregistrazione', 'conferma', 'gradimento']);
            $table->timestamp('inviata_at')->nullable();
            $table->timestamp('aperta_at')->nullable();
            $table->string('resend_message_id')->nullable();
            $table->text('errore')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['iscrizioneable_type', 'iscrizioneable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_invii');
    }
};
