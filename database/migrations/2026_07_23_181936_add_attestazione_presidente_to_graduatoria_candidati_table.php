<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->string('attestazione_presidente_path')->nullable();
            $table->boolean('attestazione_presidente_verificata')->default(false);
            $table->timestamp('attestazione_presidente_verificata_at')->nullable();
            $table->foreignId('attestazione_presidente_verificata_da_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attestazione_presidente_verificata_da_id');
            $table->dropColumn(['attestazione_presidente_path', 'attestazione_presidente_verificata', 'attestazione_presidente_verificata_at']);
        });
    }
};
