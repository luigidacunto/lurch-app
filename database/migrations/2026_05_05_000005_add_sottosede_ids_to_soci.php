<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->foreignId('sottosede_appartenenza_id')->nullable()->constrained('comitato_sottosedi')->nullOnDelete();
            $table->foreignId('sottosede_estensione_id')->nullable()->constrained('comitato_sottosedi')->nullOnDelete();
            $table->foreignId('sottosede_dipendente_id')->nullable()->constrained('comitato_sottosedi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\ComitatoSottosede::class, 'sottosede_appartenenza_id');
            $table->dropForeignIdFor(\App\Models\ComitatoSottosede::class, 'sottosede_estensione_id');
            $table->dropForeignIdFor(\App\Models\ComitatoSottosede::class, 'sottosede_dipendente_id');
            $table->dropColumn(['sottosede_appartenenza_id', 'sottosede_estensione_id', 'sottosede_dipendente_id']);
        });
    }
};
