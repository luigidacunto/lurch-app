<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduatorie', function (Blueprint $table) {
            $table->id();
            $table->string('titolo');
            $table->string('codice_protocollo')->unique();
            $table->string('slug')->unique();
            $table->foreignId('corso_id')->nullable()->constrained('corsi')->nullOnDelete();
            $table->foreignId('comitato_regionale_id')->constrained('comitati_regionali');
            $table->foreignId('comitato_locale_id')->nullable()->constrained('comitati_locali')->nullOnDelete();
            $table->dateTime('data_apertura');
            $table->dateTime('data_chiusura');
            $table->text('testo_email_conferma')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE graduatorie ADD CONSTRAINT graduatorie_date_check CHECK (data_chiusura >= data_apertura)');
    }

    public function down(): void
    {
        Schema::dropIfExists('graduatorie');
    }
};
