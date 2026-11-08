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
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->boolean('richiede_diploma')->default(false);
        });

        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->boolean('diploma_autodichiarato')->default(false);
            $table->timestamp('diploma_verificato_at')->nullable();
            $table->foreignId('diploma_verificato_da_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diploma_verificato_da_id');
            $table->dropColumn(['diploma_autodichiarato', 'diploma_verificato_at']);
        });

        Schema::table('graduatorie', function (Blueprint $table) {
            $table->dropColumn('richiede_diploma');
        });
    }
};
