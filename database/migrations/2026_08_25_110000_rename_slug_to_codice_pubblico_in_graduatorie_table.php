<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Esclude 0/O/1/I/L: caratteri facilmente confondibili quando il codice viene letto/dettato a voce.
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function up(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->renameColumn('slug', 'codice_pubblico');
        });

        // Lo slug precedente era derivato dal codice_protocollo (link non stabile se il
        // corso/protocollo cambia): rigenerato per tutte le graduatorie esistenti con un
        // codice breve indipendente. Le graduatorie già chiuse in produzione possono
        // rompere link già distribuiti — scelta esplicita, confermata (25/08/2026).
        foreach (DB::table('graduatorie')->pluck('id') as $id) {
            DB::table('graduatorie')->where('id', $id)->update([
                'codice_pubblico' => $this->generaCodiceUnivoco(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->renameColumn('codice_pubblico', 'slug');
        });
    }

    private function generaCodiceUnivoco(): string
    {
        do {
            $codice = collect(range(1, 10))
                ->map(fn () => self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)])
                ->implode('');
        } while (DB::table('graduatorie')->where('codice_pubblico', $codice)->exists());

        return $codice;
    }
};
