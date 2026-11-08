<x-mail::message>
@php
    $iscrizione   = $iscrizione;
    $partecipante = $iscrizione->partecipante;
    $corso        = $iscrizione->corso;
    $evento       = $corso->evento;
    $link         = route('form.show', $iscrizione->token);
@endphp

# Ciao {{ $partecipante->nome }} {{ $partecipante->cognome }},

sei iscritto al corso **{{ $corso->nome }}**
@if($corso->numero_protocollo) ({{ $corso->numero_protocollo }})@endif
nell'ambito dell'evento **{{ $evento->nome }}**.

**Date in presenza:** {{ \App\Models\Evento::formattaGiorni($corso->giorniInPresenza()) }}
**Sede:** {{ $corso->sede ?? $evento->sede ?? '—' }}

---

Per completare la preregistrazione, compila il form dedicato cliccando sul pulsante qui sotto. Troverai le sezioni relative alle presenze, al vitto e ai consensi richiesti.

**Il link è personale** — non condividerlo con altri partecipanti.

<x-mail::button :url="$link" color="red">
Compila il form di preregistrazione
</x-mail::button>

Se il pulsante non funziona, copia e incolla questo indirizzo nel browser:
{{ $link }}

---

Croce Rossa Italiana · {{ $evento->comitatoLocale?->nome ?? $evento->comitatoRegionale?->nome ?? 'Comitato organizzatore' }}
</x-mail::message>
