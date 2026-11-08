<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->boolean('richiede_documento_aggiuntivo')->default(false);
            $table->text('dicitura_documento_aggiuntivo')->nullable();
        });

        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->string('documento_aggiuntivo_path')->nullable();
            $table->boolean('documento_aggiuntivo_verificato')->default(false);
            $table->timestamp('documento_aggiuntivo_verificato_at')->nullable();
            $table->foreignId('documento_aggiuntivo_verificato_da_id')->nullable()
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->dropConstrainedForeignId('documento_aggiuntivo_verificato_da_id');
            $table->dropColumn([
                'documento_aggiuntivo_path',
                'documento_aggiuntivo_verificato',
                'documento_aggiuntivo_verificato_at',
            ]);
        });

        Schema::table('corsi', function (Blueprint $table) {
            $table->dropColumn(['richiede_documento_aggiuntivo', 'dicitura_documento_aggiuntivo']);
        });
    }
};
