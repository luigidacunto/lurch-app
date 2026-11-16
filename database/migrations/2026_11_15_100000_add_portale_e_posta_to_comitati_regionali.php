<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurazione per regione: dominio del portale, branding, responsabile privacy
 * e account SMTP. Tutti facoltativi: senza valori valgono quelli di .env
 * (tranne la posta degli eventi, che senza account resta bloccata).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comitati_regionali', function (Blueprint $table) {
            $table->string('url_portale')->nullable()->unique();
            $table->string('logo')->nullable();
            $table->string('privacy_responsabile')->nullable();
            $table->string('mail_host')->nullable();
            $table->unsignedInteger('mail_port')->nullable();
            $table->string('mail_scheme', 10)->nullable();
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comitati_regionali', function (Blueprint $table) {
            $table->dropUnique(['url_portale']);
            $table->dropColumn([
                'url_portale', 'logo', 'privacy_responsabile',
                'mail_host', 'mail_port', 'mail_scheme', 'mail_username', 'mail_password',
                'mail_from_address', 'mail_from_name',
            ]);
        });
    }
};
