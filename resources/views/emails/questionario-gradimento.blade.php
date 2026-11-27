<x-mail::message>
# Ciao {{ $partecipante->nome }} {{ $partecipante->cognome }},

grazie per aver partecipato al corso **{{ $corso->nome }}**@if($corso->numero_protocollo) ({{ $corso->numero_protocollo }})@endif.

Ti chiediamo qualche minuto per il **questionario di gradimento**: la tua opinione su organizzazione, logistica e docenti ci aiuta a migliorare i prossimi corsi.

**Il questionario è completamente anonimo.** Le risposte vengono salvate senza nessun collegamento al tuo nome: chi organizza il corso vede solo i risultati complessivi, e soltanto quando hanno risposto almeno 5 persone. Si compila una volta sola.

**Puoi compilarlo fino al {{ $chiudeIl->format('d/m/Y') }}.**

<x-mail::button :url="$link" color="red">
Compila il questionario
</x-mail::button>

Il questionario si trova nell'app dei soci: entri con la tua email e il codice che ricevi, e lo trovi nella pagina iniziale. Se il pulsante non funziona, apri questo indirizzo:
{{ $linkApp }}

Ti consigliamo di installare l'app sul telefono: lì trovi anche le informazioni dei tuoi eventi e corsi.
@if($fraseEsame)

Nell'app puoi consultare anche i **risultati del tuo esame** già pubblicati.
@endif

---

Croce Rossa Italiana · {{ $evento->comitatoLocale?->nome ?? $evento->comitatoRegionale?->nome ?? 'Comitato organizzatore' }}
</x-mail::message>
