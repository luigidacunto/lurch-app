<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_verifiche', function (Blueprint $table) {
            // Indirizzo target esplicito della verifica (può differire da soci.email
            // quando si tratta di un cambio email in attesa di conferma).
            $table->string('email')->nullable()->after('socio_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_verifiche', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
