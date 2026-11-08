<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualifiche', function (Blueprint $table) {
            $table->dropColumn('mesi_validita');

            // Tipo di scadenza: nessuna / mesi_esatti / anno_solare
            $table->string('scadenza_tipo', 20)->nullable()->after('attivo')
                ->comment('nessuna | mesi_esatti | anno_solare');

            // Durata: mesi (se mesi_esatti) o anni (se anno_solare)
            $table->unsignedSmallInteger('scadenza_durata')->nullable()->after('scadenza_tipo')
                ->comment('Mesi se mesi_esatti, anni se anno_solare');
        });
    }

    public function down(): void
    {
        Schema::table('qualifiche', function (Blueprint $table) {
            $table->dropColumn(['scadenza_tipo', 'scadenza_durata']);
            $table->unsignedSmallInteger('mesi_validita')->nullable();
        });
    }
};
