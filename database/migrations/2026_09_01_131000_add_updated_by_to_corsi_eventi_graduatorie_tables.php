<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Chi ha fatto l'ultima modifica al record. Popolato automaticamente dal trait
    // App\Traits\TracksUpdatedBy (auth()->id() quando c'è una sessione admin). NULL da
    // scritture console/import e dalla PWA (che usa app_socio_id, non il guard auth).
    // Campo interno diagnostico, non mostrato nei pannelli.
    private array $tabelle = ['corsi', 'eventi', 'graduatorie'];

    public function up(): void
    {
        foreach ($this->tabelle as $tabella) {
            Schema::table($tabella, function (Blueprint $table) {
                $table->foreignId('updated_by')->nullable()->after('updated_at')
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabelle as $tabella) {
            Schema::table($tabella, function (Blueprint $table) {
                $table->dropConstrainedForeignId('updated_by');
            });
        }
    }
};
