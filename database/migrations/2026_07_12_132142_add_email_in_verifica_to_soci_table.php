<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            // Email non ancora verificata: quando cambia via form pubblico, 'email' resta
            // intatta finché la nuova non viene confermata (link o codice).
            $table->string('email_in_verifica')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->dropColumn('email_in_verifica');
        });
    }
};
