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
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->boolean('pagamento_verificato')->default(false)->after('ricevuta_path');
            $table->timestamp('pagamento_verificato_at')->nullable()->after('pagamento_verificato');
            $table->foreignId('pagamento_verificato_da_id')
                ->nullable()
                ->after('pagamento_verificato_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->dropForeign(['pagamento_verificato_da_id']);
            $table->dropColumn(['pagamento_verificato', 'pagamento_verificato_at', 'pagamento_verificato_da_id']);
        });
    }
};
