<x-mail::message>
@php
    $iscrizione   = $iscrizione;
    $partecipante = $iscrizione->partecipante;
    $evento       = $iscrizione->evento;
    $link         = route('form-staff.show', $iscrizione->token);
@endphp

# Ciao {{ $partecipante->nome }} {{ $partecipante->cognome }},

sei stato inserito nello **staff** dell'evento **{{ $evento->nome }}**.

**Date:** {{ \App\Models\Evento::formattaGiorni($evento->giorniEvento()) }}
@if($evento->sede)**Sede:** {{ $evento->sede }}@endif

---

Per completare la preregistrazione come staff, compila il form dedicato cliccando sul pulsante qui sotto. Troverai le sezioni relative alle disponibilità, ai pasti e ai consensi richiesti.

**Il link è personale** — non condividerlo.

<x-mail::button :url="$link" color="red">
Compila il form staff
</x-mail::button>

Se il pulsante non funziona, copia e incolla questo indirizzo nel browser:
{{ $link }}

---

Croce Rossa Italiana · {{ $evento->comitatoLocale?->nome ?? $evento->comitatoRegionale?->nome ?? 'Comitato organizzatore' }}
</x-mail::message>
