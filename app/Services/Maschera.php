<?php

namespace App\Services;

/**
 * Oscura dati di contatto (email/telefono) per mostrarli in form pubblici senza
 * middleware di autenticazione (es. iscrizione graduatoria via solo CF), dove
 * esporre il dato reale sarebbe un rischio privacy per chiunque conosca il CF.
 *
 * Il numero di asterischi è SEMPRE fisso, indipendente dalla lunghezza reale del
 * dato: la lunghezza è di per sé un'informazione che riduce lo spazio di ricerca
 * per chi tenta di indovinare il valore originale.
 */
class Maschera
{
    public static function email(?string $email): ?string
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return $email;
        }

        [$locale, $dominio] = explode('@', $email, 2);

        $tld = mb_substr(strrchr($dominio, '.') ?: '.it', 1);

        $primoLocale  = mb_substr($locale, 0, 1);
        $primoDominio = mb_substr($dominio, 0, 1);

        return "{$primoLocale}****@{$primoDominio}****.{$tld}";
    }

    public static function telefono(?string $telefono): ?string
    {
        if (blank($telefono)) {
            return $telefono;
        }

        $pulito = preg_replace('/\D+/', '', $telefono);

        $primi  = mb_substr($pulito, 0, 3);
        $ultimi = mb_substr($pulito, -2);

        return "{$primi}****{$ultimi}";
    }
}
