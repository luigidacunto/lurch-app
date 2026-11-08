<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Uppercase tutti i CF in soci
        DB::statement('UPDATE soci SET codice_fiscale = UPPER(codice_fiscale)');

        // 2. Deduplica comitati_locali case-insensitive (per nome+regionale)
        //    Tiene il record con id più basso, re-punta tutte le FK al kept_id, soft-delete i duplicati.
        DB::statement("
            WITH dupes AS (
                SELECT
                    MIN(id)                            AS keep_id,
                    ARRAY_AGG(id ORDER BY id)          AS all_ids,
                    LOWER(nome)                        AS nome_lower,
                    comitato_regionale_id
                FROM comitati_locali
                WHERE deleted_at IS NULL
                GROUP BY LOWER(nome), comitato_regionale_id
                HAVING COUNT(*) > 1
            )
            UPDATE soci
            SET comitato_locale_id = d.keep_id
            FROM dupes d
            WHERE soci.comitato_locale_id = ANY(d.all_ids)
              AND soci.comitato_locale_id <> d.keep_id
        ");

        DB::statement("
            WITH dupes AS (
                SELECT MIN(id) AS keep_id, ARRAY_AGG(id ORDER BY id) AS all_ids,
                       LOWER(nome) AS nome_lower, comitato_regionale_id
                FROM comitati_locali WHERE deleted_at IS NULL
                GROUP BY LOWER(nome), comitato_regionale_id HAVING COUNT(*) > 1
            )
            UPDATE soci SET comitato_locale_estensione_id = d.keep_id
            FROM dupes d
            WHERE soci.comitato_locale_estensione_id = ANY(d.all_ids)
              AND soci.comitato_locale_estensione_id <> d.keep_id
        ");

        DB::statement("
            WITH dupes AS (
                SELECT MIN(id) AS keep_id, ARRAY_AGG(id ORDER BY id) AS all_ids,
                       LOWER(nome) AS nome_lower, comitato_regionale_id
                FROM comitati_locali WHERE deleted_at IS NULL
                GROUP BY LOWER(nome), comitato_regionale_id HAVING COUNT(*) > 1
            )
            UPDATE soci SET comitato_locale_dipendente_id = d.keep_id
            FROM dupes d
            WHERE soci.comitato_locale_dipendente_id = ANY(d.all_ids)
              AND soci.comitato_locale_dipendente_id <> d.keep_id
        ");

        DB::statement("
            WITH dupes AS (
                SELECT MIN(id) AS keep_id, ARRAY_AGG(id ORDER BY id) AS all_ids,
                       LOWER(nome) AS nome_lower, comitato_regionale_id
                FROM comitati_locali WHERE deleted_at IS NULL
                GROUP BY LOWER(nome), comitato_regionale_id HAVING COUNT(*) > 1
            )
            UPDATE comitato_sottosedi SET comitato_locale_id = d.keep_id
            FROM dupes d
            WHERE comitato_sottosedi.comitato_locale_id = ANY(d.all_ids)
              AND comitato_sottosedi.comitato_locale_id <> d.keep_id
        ");

        // Soft-delete i duplicati di comitati_locali (ora irraggiungibili da FK)
        DB::statement("
            WITH dupes AS (
                SELECT MIN(id) AS keep_id, ARRAY_AGG(id ORDER BY id) AS all_ids,
                       LOWER(nome) AS nome_lower, comitato_regionale_id
                FROM comitati_locali WHERE deleted_at IS NULL
                GROUP BY LOWER(nome), comitato_regionale_id HAVING COUNT(*) > 1
            )
            UPDATE comitati_locali
            SET deleted_at = NOW()
            WHERE id IN (
                SELECT UNNEST(all_ids) FROM dupes
            )
            AND id NOT IN (SELECT keep_id FROM dupes)
        ");

        // 3. Dopo il re-puntamento dei comitati, potrebbero esserci doppioni in comitato_sottosedi
        //    (stesso nome case-insensitive per stesso locale). Re-punta soci e cancella.
        DB::statement("
            WITH sub_dupes AS (
                SELECT
                    MIN(id)                   AS keep_id,
                    ARRAY_AGG(id ORDER BY id) AS all_ids,
                    LOWER(nome)               AS nome_lower,
                    comitato_locale_id
                FROM comitato_sottosedi
                GROUP BY LOWER(nome), comitato_locale_id
                HAVING COUNT(*) > 1
            )
            UPDATE soci SET sottosede_appartenenza_id = d.keep_id
            FROM sub_dupes d
            WHERE soci.sottosede_appartenenza_id = ANY(d.all_ids)
              AND soci.sottosede_appartenenza_id <> d.keep_id
        ");

        DB::statement("
            WITH sub_dupes AS (
                SELECT MIN(id) AS keep_id, ARRAY_AGG(id ORDER BY id) AS all_ids,
                       LOWER(nome) AS nome_lower, comitato_locale_id
                FROM comitato_sottosedi
                GROUP BY LOWER(nome), comitato_locale_id HAVING COUNT(*) > 1
            )
            UPDATE soci SET sottosede_estensione_id = d.keep_id
            FROM sub_dupes d
            WHERE soci.sottosede_estensione_id = ANY(d.all_ids)
              AND soci.sottosede_estensione_id <> d.keep_id
        ");

        DB::statement("
            WITH sub_dupes AS (
                SELECT MIN(id) AS keep_id, ARRAY_AGG(id ORDER BY id) AS all_ids,
                       LOWER(nome) AS nome_lower, comitato_locale_id
                FROM comitato_sottosedi
                GROUP BY LOWER(nome), comitato_locale_id HAVING COUNT(*) > 1
            )
            UPDATE soci SET sottosede_dipendente_id = d.keep_id
            FROM sub_dupes d
            WHERE soci.sottosede_dipendente_id = ANY(d.all_ids)
              AND soci.sottosede_dipendente_id <> d.keep_id
        ");

        DB::statement("
            DELETE FROM comitato_sottosedi
            WHERE id IN (
                SELECT UNNEST(all_ids) FROM (
                    SELECT ARRAY_AGG(id ORDER BY id) AS all_ids, MIN(id) AS keep_id,
                           LOWER(nome) AS nome_lower, comitato_locale_id
                    FROM comitato_sottosedi
                    GROUP BY LOWER(nome), comitato_locale_id
                    HAVING COUNT(*) > 1
                ) sub
            )
            AND id NOT IN (
                SELECT keep_id FROM (
                    SELECT MIN(id) AS keep_id, LOWER(nome) AS nome_lower, comitato_locale_id
                    FROM comitato_sottosedi
                    GROUP BY LOWER(nome), comitato_locale_id
                    HAVING COUNT(*) > 1
                ) kept
            )
        ");

        // 4. Unique constraint case-insensitive su comitato_sottosedi
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS comitato_sottosedi_locale_nome_lower_unique
            ON comitato_sottosedi (comitato_locale_id, LOWER(nome))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS comitato_sottosedi_locale_nome_lower_unique');
    }
};
