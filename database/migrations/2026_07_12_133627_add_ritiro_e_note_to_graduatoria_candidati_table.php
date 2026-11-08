<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->text('note')->nullable();
            $table->timestamp('ritirato_at')->nullable();
            $table->foreignId('ritirato_da_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_ritiro')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ritirato_da_id');
            $table->dropColumn(['note', 'ritirato_at', 'motivo_ritiro']);
        });
    }
};
