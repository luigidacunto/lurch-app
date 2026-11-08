<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->boolean('nullaosta_verificato')->default(false)->after('nullaosta_path');
            $table->timestamp('nullaosta_verificato_at')->nullable()->after('nullaosta_verificato');
            $table->foreignId('nullaosta_verificato_da_id')->nullable()->after('nullaosta_verificato_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nullaosta_verificato_da_id');
            $table->dropColumn(['nullaosta_verificato', 'nullaosta_verificato_at']);
        });
    }
};
