<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iscrizioni', function (Blueprint $table) {
            $table->timestamp('sollecito_inviato_at')->nullable()->after('form_inviato_at');
        });

        Schema::table('iscrizioni_staff', function (Blueprint $table) {
            $table->timestamp('sollecito_inviato_at')->nullable()->after('form_inviato_at');
        });

        Schema::table('corsi', function (Blueprint $table) {
            $table->timestamp('invio_massivo_sollecito_iscritti_at')->nullable()->after('invio_massivo_iscritti_at');
        });

        Schema::table('eventi', function (Blueprint $table) {
            $table->timestamp('invio_massivo_sollecito_staff_at')->nullable()->after('invio_massivo_staff_at');
        });
    }

    public function down(): void
    {
        Schema::table('iscrizioni', fn (Blueprint $t) => $t->dropColumn('sollecito_inviato_at'));
        Schema::table('iscrizioni_staff', fn (Blueprint $t) => $t->dropColumn('sollecito_inviato_at'));
        Schema::table('corsi', fn (Blueprint $t) => $t->dropColumn('invio_massivo_sollecito_iscritti_at'));
        Schema::table('eventi', fn (Blueprint $t) => $t->dropColumn('invio_massivo_sollecito_staff_at'));
    }
};
