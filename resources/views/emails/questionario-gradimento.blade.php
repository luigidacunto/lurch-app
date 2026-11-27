{{-- Corpo dell'invito al questionario (frammento HTML dentro emails.template-corpo, che aggiunge intestazione e piè di pagina). --}}
<h2>Ciao {{ $partecipante->nome }} {{ $partecipante->cognome }},</h2>

<p>grazie per aver partecipato al corso <strong>{{ $corso->nome }}</strong>@if($corso->numero_protocollo) ({{ $corso->numero_protocollo }})@endif.</p>

<p>Ti chiediamo qualche minuto per il <strong>questionario di gradimento</strong>: la tua opinione su organizzazione, logistica e docenti ci aiuta a migliorare i prossimi corsi.</p>

<p><strong>Il questionario è completamente anonimo.</strong> Le risposte vengono salvate senza nessun collegamento al tuo nome: chi organizza il corso vede solo i risultati complessivi, e soltanto quando hanno risposto almeno 5 persone. Si compila una volta sola.</p>

<p><strong>Puoi compilarlo fino al {{ $chiudeIl->format('d/m/Y') }}.</strong></p>

<p style="text-align:center; margin:24px 0;">
    <a href="{{ $link }}" style="display:inline-block; background:#dc2626; color:#ffffff; text-decoration:none; font-weight:600; padding:12px 24px; border-radius:6px;">Compila il questionario</a>
</p>

<p>Il questionario si trova nell'app dei soci: entri con la tua email e il codice che ricevi, e lo trovi nella pagina iniziale. Se il pulsante non funziona, apri questo indirizzo: <a href="{{ $linkApp }}">{{ $linkApp }}</a></p>

<p>Ti consigliamo di installare l'app sul telefono: lì trovi anche le informazioni dei tuoi eventi e corsi.</p>
@if($fraseEsame)

<p>{!! $fraseEsame !!}</p>
@endif
