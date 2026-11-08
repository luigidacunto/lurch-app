<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('qualifiche', 'catalogo_corsi');
        Schema::rename('qualifica_validita_regionali', 'catalogo_corsi_validita_regionali');
        Schema::rename('socio_qualifiche', 'qualifiche_soci');
    }

    public function down(): void
    {
        Schema::rename('catalogo_corsi', 'qualifiche');
        Schema::rename('catalogo_corsi_validita_regionali', 'qualifica_validita_regionali');
        Schema::rename('qualifiche_soci', 'socio_qualifiche');
    }
};
