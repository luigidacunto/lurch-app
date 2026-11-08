@extends('app.layout')

@section('title', 'Test scritto')

@php
    $totale = count($domande);
    $date = count(array_filter($scelte, fn ($v) => $v !== null));
    $lettere = ['A', 'B', 'C', 'D'];
@endphp

@push('styles')
@include('app.partials.esami-stili')
<style>
    .domanda { background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    .domanda-num { font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
    .domanda-testo { font-size: 16px; font-weight: 600; color: #111827; line-height: 1.4; overflow-wrap: anywhere; white-space: pre-wrap; margin-bottom: 10px; }
    .opzione {
        display: flex; align-items: flex-start; gap: 10px; width: 100%; text-align: left; box-sizing: border-box;
        min-height: 52px; padding: 10px 12px; margin-bottom: 8px; border-radius: 10px; border: 2px solid #e5e7eb;
        background: #fff; font-size: 15px; color: #1f2937; cursor: pointer; touch-action: manipulation; font-family: inherit;
    }
    .opzione .lettera { flex: none; width: 26px; height: 26px; border-radius: 50%; background: #f3f4f6; color: #4b5563; font-weight: 800; font-size: 13px; display: flex; align-items: center; justify-content: center; }
    .opzione .testo { overflow-wrap: anywhere; white-space: pre-wrap; line-height: 1.35; padding-top: 3px; }
    .opzione.sel { border-color: #1d4ed8; background: #eff6ff; }
    .opzione.sel .lettera { background: #1d4ed8; color: #fff; }
    .btn-invia { width: 100%; min-height: 48px; border: 0; border-radius: 10px; background: #15803d; color: #fff; font-size: 16px; font-weight: 800; cursor: pointer; }
    .btn-invia:disabled { background: #d1d5db; color: #6b7280; cursor: not-allowed; }
    .motivo-invio { font-size: 12px; color: #6b7280; text-align: center; margin-top: 6px; min-height: 16px; }
    /* margin:auto: il reset globale del layout (* { margin: 0 }) toglie il centramento nativo del <dialog> */
    dialog.conferma { margin: auto; border: 0; border-radius: 14px; padding: 20px; max-width: 360px; width: calc(100% - 32px); box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
    dialog.conferma::backdrop { background: rgba(0,0,0,0.5); }
    dialog.conferma h3 { font-size: 17px; font-weight: 800; margin-bottom: 8px; color: #111827; }
    dialog.conferma p { font-size: 14px; color: #4b5563; line-height: 1.4; margin-bottom: 16px; }
    .conferma-azioni { display: flex; gap: 8px; }
    .conferma-azioni button { flex: 1; min-height: 44px; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; border: 2px solid #e5e7eb; background: #fff; color: #374151; }
    .conferma-azioni button.ok { background: #15803d; border-color: #15803d; color: #fff; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esami') }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    I tuoi esami
</a>
<p class="page-title">Test scritto</p>
<p class="page-sub">{{ $esame->titolo }}</p>

<div class="avviso-personale">{{ $regola }}</div>
<div id="avviso-errore" class="avviso-errore" style="display:none"></div>

@forelse($domande as $i => $d)
    <div class="domanda" data-domanda="{{ $d['id'] }}">
        <div class="domanda-num">Domanda {{ $i + 1 }} di {{ $totale }}</div>
        <div class="domanda-testo">{{ $d['testo'] }}</div>
        {{-- $k = indice originale dell'opzione (id stabile, è ciò che si salva); la lettera segue la posizione mostrata --}}
        @foreach($d['opzioni'] as $k => $testo)
            <button type="button" class="opzione {{ ($scelte[$d['id']] ?? null) === $k ? 'sel' : '' }}" data-opzione="{{ $k }}"
                    aria-pressed="{{ ($scelte[$d['id']] ?? null) === $k ? 'true' : 'false' }}">
                {{-- Mescolato: niente lettere (cambierebbero a ogni caricamento e confonderebbero), solo il tondino di selezione --}}
                <span class="lettera">{{ $mescola ? '' : ($lettere[$loop->index] ?? $loop->index + 1) }}</span><span class="testo">{{ $testo }}</span>
            </button>
        @endforeach
    </div>
@empty
    <div class="vuoto">Il test non ha domande.</div>
@endforelse

<form id="form-invia" method="POST" action="{{ route('app.esame.test.invia', $esame) }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
</form>

<div class="barra">
    <div class="barra-riga">
        <span>Risposte date <span id="conta-date">{{ $date }}</span> su {{ $totale }}</span>
        <span id="stato-salvataggio" class="barra-stato">Ogni risposta si salva da sola</span>
    </div>
    <button type="button" id="btn-invia" class="btn-invia">Invia il test</button>
    <div class="motivo-invio" id="motivo-invio"></div>
</div>

<dialog class="conferma" id="dialogo-conferma">
    <h3>Inviare il test?</h3>
    <p>Dopo l'invio non puoi più modificare le risposte né rifare il test. Hai dato <strong id="conferma-date">{{ $date }}</strong> risposte su {{ $totale }}.</p>
    <div class="conferma-azioni">
        <button type="button" id="conferma-annulla">Torna al test</button>
        <button type="button" id="conferma-ok" class="ok">Sì, invia</button>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
(function () {
    var url = {!! json_encode($urlRisposta) !!};
    var csrf = {!! json_encode(csrf_token()) !!};
    var token = {!! json_encode($token) !!};
    var obbligatorie = {!! json_encode($obbligatorie) !!};
    var totale = {{ $totale }};
    var stato = document.getElementById('stato-salvataggio');
    var avviso = document.getElementById('avviso-errore');
    var btn = document.getElementById('btn-invia');
    var motivo = document.getElementById('motivo-invio');
    var dialogo = document.getElementById('dialogo-conferma');
    var date = {{ $date }};
    var bloccato = false;

    function aggiorna() {
        document.getElementById('conta-date').textContent = date;
        document.getElementById('conferma-date').textContent = date;
        var manca = obbligatorie && date < totale;
        btn.disabled = bloccato || totale === 0 || manca;
        motivo.textContent = manca ? 'Rispondi a tutte le domande per poter inviare (mancano ' + (totale - date) + ').' : '';
    }
    function errore(msg, blocca) {
        avviso.textContent = msg;
        avviso.style.display = 'block';
        if (blocca) { bloccato = true; aggiorna(); }
    }
    function segna(box, k) {
        box.querySelectorAll('.opzione').forEach(function (b) {
            var sel = b.dataset.opzione === String(k);
            b.classList.toggle('sel', sel);
            b.setAttribute('aria-pressed', sel ? 'true' : 'false');
        });
    }
    function scelta(box) {
        var b = box.querySelector('.opzione.sel');
        return b ? b.dataset.opzione : null;
    }

    document.querySelectorAll('.domanda').forEach(function (box) {
        box.querySelectorAll('.opzione').forEach(function (b) {
            b.addEventListener('click', function () {
                if (bloccato) return;
                var prima = scelta(box);
                var nuova = b.dataset.opzione;
                if (prima === nuova) {
                    // Togliere la scelta è ammesso solo con le penalità attive (il server lo rifiuta altrimenti).
                    if (obbligatorie) return;
                    nuova = null;
                }
                segna(box, nuova);
                stato.className = 'barra-stato'; stato.textContent = 'Salvo…';
                fetch(url, {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify({token: token, domanda: parseInt(box.dataset.domanda, 10), opzione: nuova === null ? null : parseInt(nuova, 10)}),
                    credentials: 'same-origin'
                }).then(function (r) {
                    return r.json().then(function (d) { return {ok: r.ok, status: r.status, d: d}; });
                }).then(function (r) {
                    if (!r.ok) {
                        segna(box, prima);
                        stato.className = 'barra-stato errore'; stato.textContent = 'Non salvato';
                        errore((r.d && r.d.errore) || 'Risposta non salvata, riprova.', r.status === 409);
                        return;
                    }
                    date = r.d.date; avviso.style.display = 'none';
                    stato.textContent = 'Salvato'; aggiorna();
                }).catch(function () {
                    segna(box, prima);
                    stato.className = 'barra-stato errore'; stato.textContent = 'Non salvato — controlla la connessione';
                });
            });
        });
    });

    btn.addEventListener('click', function () { if (!btn.disabled) dialogo.showModal(); });
    // La conferma non si chiude con ESC né con un clic fuori: solo con i due pulsanti.
    dialogo.addEventListener('cancel', function (e) { e.preventDefault(); });
    document.getElementById('conferma-annulla').addEventListener('click', function () { dialogo.close(); });
    document.getElementById('conferma-ok').addEventListener('click', function () {
        this.disabled = true; btn.disabled = true;
        document.getElementById('form-invia').submit();
    });
    aggiorna();
})();
</script>
@endpush
