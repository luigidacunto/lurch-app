<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corsi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->string('nome');
            $table->text('descrizione');
            $table->string('docente')->nullable();
            $table->integer('posti_disponibili')->nullable();
            $table->date('data_inizio');
            $table->time('ora_inizio');
            $table->date('data_fine');
            $table->time('ora_fine');
            $table->boolean('residenziale')->default(false);
            $table->boolean('pagamento_previsto')->default(false);
            $table->jsonb('form_sections')->default('[]');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corsi');
    }
};
