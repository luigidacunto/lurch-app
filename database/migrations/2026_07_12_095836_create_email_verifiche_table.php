<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verifica email socio: doppia via sullo stesso record.
 * - `codice`: 6 cifre (hash bcrypt, come otp_codes) da digitare a mano.
 * - `token`: stringa lunga in chiaro, usata nel link cliccabile dell'email
 *   (verifica automatica al click, nessuna digitazione richiesta).
 * Qualsiasi delle due vie completi per prima marca `used_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verifiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('soci')->cascadeOnDelete();
            $table->string('codice'); // hash bcrypt, 6 cifre
            $table->string('token', 64)->unique(); // in chiaro, per il link
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->integer('tentativi_falliti')->default(0);
            $table->timestamp('bloccato_fino_a')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifiche');
    }
};
