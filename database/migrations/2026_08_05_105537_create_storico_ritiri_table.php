<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storico_ritiri', function (Blueprint $table) {
            $table->id();
            $table->morphs('ritirabile'); // ritirabile_type + ritirabile_id + indice
            $table->enum('tipo', ['ritiro', 'ripristino']);
            $table->text('motivo')->nullable();
            $table->foreignId('eseguito_da_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storico_ritiri');
    }
};
