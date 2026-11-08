@php
    $nVoci = count($struttura['codici']);
    $nCompilate = count($valori);
    $completa = $nCompilate === $nVoci;
@endphp
<div class="barra">
    <div class="barra-riga">
        <span id="contatore">{{ $nCompilate }}/{{ $nVoci }} voci</span>
        <span id="stato-salvataggio" class="barra-stato">{{ $modificabile ? 'Ogni tocco si salva da solo' : '' }}</span>
    </div>
    <div class="totale" id="totale" @if(! $totale) hidden @endif>
        Totale della tua scheda:<strong id="totale-valore">@if($totale){{ $totale['punti'] }} su {{ $totale['massimo'] }}@endif</strong>
    </div>
    @if($modificabile)
        <form method="POST" action="{{ $urlInvia }}">
            @csrf
            <button type="submit" class="btn-invia" id="btn-invia" @disabled(! $completa)>{{ $inviata ? 'Reinvia scheda' : 'Invia scheda' }}</button>
        </form>
        <div class="barra-motivo" id="motivo-invia" @if($completa) hidden @endif>Compila tutte le {{ $nVoci }} voci per poter inviare la scheda.</div>
    @endif
</div>
@if($modificabile)
@push('scripts')
<script>
(function () {
    var url = {!! json_encode($urlVoce) !!};
    var token = {!! json_encode(csrf_token()) !!};
    var nVoci = {{ $nVoci }};
    var stato = document.getElementById('stato-salvataggio');
    var contatore = document.getElementById('contatore');
    var btnInvia = document.getElementById('btn-invia');
    var motivo = document.getElementById('motivo-invia');
    var totale = document.getElementById('totale');
    var totaleValore = document.getElementById('totale-valore');

    function segna(box, livello) {
        box.querySelectorAll('button').forEach(function (b) {
            var sel = b.dataset.livello === livello;
            b.classList.toggle('sel', sel);
            b.setAttribute('aria-pressed', sel ? 'true' : 'false');
        });
    }
    function corrente(box) {
        var b = box.querySelector('button.sel');
        return b ? b.dataset.livello : null;
    }
    function aggiornaConta(box) {
        var comp = box.dataset.comp;
        var tutte = document.querySelectorAll('[data-comp="' + comp + '"]');
        var fatte = 0;
        tutte.forEach(function (b) { if (b.querySelector('button.sel')) fatte++; });
        var el = document.querySelector('[data-conta-comp="' + comp + '"]');
        el.textContent = fatte + '/' + tutte.length;
        el.classList.toggle('ok', fatte === tutte.length);
    }
    function aggiornaTotali(compilate, tot) {
        contatore.textContent = compilate + '/' + nVoci + ' voci';
        var completa = compilate === nVoci;
        btnInvia.disabled = !completa;
        motivo.hidden = completa;
        totale.hidden = !tot;
        if (tot) totaleValore.textContent = tot.punti + ' su ' + tot.massimo;
    }

    document.querySelectorAll('.semaforo button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = btn.closest('.voce');
            var precedente = corrente(box);
            if (precedente === btn.dataset.livello) return;
            segna(box, btn.dataset.livello);
            stato.className = 'barra-stato';
            stato.textContent = 'Salvo…';
            fetch(url, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
                body: JSON.stringify({voce: btn.dataset.voce, valore: btn.dataset.livello}),
                credentials: 'same-origin'
            }).then(function (r) {
                if (!r.ok) throw new Error(r.status);
                return r.json();
            }).then(function (d) {
                stato.textContent = 'Salvato';
                aggiornaConta(box);
                aggiornaTotali(d.compilate, d.totale);
            }).catch(function () {
                segna(box, precedente);
                stato.className = 'barra-stato errore';
                stato.textContent = 'Non salvato — riprova';
            });
        });
    });
})();
</script>
@endpush
@endif
