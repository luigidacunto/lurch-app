<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_modifiche_email', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partecipante_id')->constrained('partecipanti')->cascadeOnDelete();
            $table->string('email_vecchia');
            $table->string('email_nuova');
            $table->enum('fonte', ['import', 'admin']);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_modifiche_email');
    }
};
