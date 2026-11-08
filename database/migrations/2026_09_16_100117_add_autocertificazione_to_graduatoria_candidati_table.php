<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->string('autocertificazione_path')->nullable();
            $table->boolean('autocertificazione_verificata')->default(false);
            $table->timestamp('autocertificazione_verificata_at')->nullable();
            $table->foreignId('autocertificazione_verificata_da_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->dropConstrainedForeignId('autocertificazione_verificata_da_id');
            $table->dropColumn(['autocertificazione_path', 'autocertificazione_verificata', 'autocertificazione_verificata_at']);
        });
    }
};
