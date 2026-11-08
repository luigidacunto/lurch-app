<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('tipo', 20)->default('iscritti')->after('id');
        });

        // Unique constraint per (tipo, comitato_regionale_id) su record non-null
        \DB::statement('CREATE UNIQUE INDEX email_templates_unique_tipo_regione ON email_templates (tipo, comitato_regionale_id) WHERE comitato_regionale_id IS NOT NULL');
        // Unique constraint per tipo sul default globale (comitato_regionale_id IS NULL)
        \DB::statement('CREATE UNIQUE INDEX email_templates_unique_tipo_default ON email_templates (tipo) WHERE comitato_regionale_id IS NULL');
    }

    public function down(): void
    {
        \DB::statement('DROP INDEX IF EXISTS email_templates_unique_tipo_regione');
        \DB::statement('DROP INDEX IF EXISTS email_templates_unique_tipo_default');

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
