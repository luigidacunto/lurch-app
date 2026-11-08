{{-- Sezione «Prova finale» del dettaglio esame: in cima ai compilatori a prova aperta, altrimenti dopo l'Itinere. --}}
@if($provaFinale || $riepilogoProvaFinale)
<p class="sezione">Prova finale</p>
@if($provaFinale)
<a class="lab-card" href="{{ route('app.esame.colloquio', $esame) }}">
    <div class="lab-nome">Colloquio</div>
    <div class="lab-stato">Il tuo voto e la tua impressione per ogni discente</div>
</a>
<a class="lab-card" href="{{ route('app.esame.provapratica', $esame) }}">
    <div class="lab-nome">Prova pratica</div>
    <div class="lab-stato">La tua scheda a semaforo per ogni discente</div>
</a>
@endif
@if($riepilogoProvaFinale)
<a class="lab-card" href="{{ route('app.esame.provafinale.riepilogo', $esame) }}">
    <div class="lab-nome">Riepilogo prova finale</div>
    <div class="lab-stato">Schede dei commissari e schede finali — sola lettura</div>
</a>
@endif
@endif
