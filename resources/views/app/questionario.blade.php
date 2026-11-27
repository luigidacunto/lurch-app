@extends('app.layout')

@section('title', 'Questionario di gradimento')

@push('styles')
@include('app.partials.esami-stili')
<style>
    .box { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    .intro { font-size: 14px; color: #374151; line-height: 1.5; }
    .sez-titolo { font-size: 16px; font-weight: 800; color: #111827; margin-bottom: 12px; }
    .sez-sottotitolo { font-size: 14px; font-weight: 800; color: #374151; margin: 22px 0 10px; padding-top: 14px; border-top: 1px solid #e5e7eb; }
    .domanda { margin-bottom: 18px; }
    .domanda:last-child { margin-bottom: 0; }
    .domanda-testo { font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 8px; line-height: 1.4; }
    .scala { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 6px; }
    .opzioni { display: grid; grid-template-columns: minmax(0, 1fr); gap: 6px; }
    .opz input { position: absolute; opacity: 0; pointer-events: none; }
    .opz span { display: block; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 6px; font-size: 13px; text-align: center; color: #374151; background: #fff; cursor: pointer; }
    .opzioni .opz span { text-align: left; padding: 10px 12px; }
    /* «Sufficientemente» non entra in un quinto di schermo del telefono: va a capo sillabato. */
    .opz span small { display: block; font-size: 10px; color: #6b7280; margin-top: 2px; line-height: 1.2; hyphens: auto; -webkit-hyphens: auto; overflow-wrap: anywhere; }
    .scala .opz { display: flex; }
    .scala .opz span { flex: 1; padding: 9px 3px; }
    .opz input:checked + span { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; font-weight: 700; }
    .opz input:checked + span small { color: #1d4ed8; font-weight: 400; }
    .opz input:focus-visible + span { outline: 2px solid #3b82f6; outline-offset: 2px; }
    textarea, input[type=text] { width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 10px 12px; font: inherit; font-size: 14px; }
    textarea { min-height: 96px; resize: vertical; }
    .errore { color: #b91c1c; font-size: 12px; margin-top: 4px; }
    .domanda.ha-errore .domanda-testo { color: #b91c1c; }
    .btn-invia { width: 100%; background: #C8102E; color: #fff; border: 0; border-radius: 12px; padding: 14px; font-size: 16px; font-weight: 700; cursor: pointer; }
    .btn-invia:disabled { opacity: .6; }
    .legenda { font-size: 12px; color: #6b7280; margin-top: 8px; }
</style>
@endpush

@section('content')
<a href="{{ route('app.home') }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    I miei eventi
</a>
<p class="page-title">Questionario di gradimento</p>
<p class="page-sub">Discenti – {{ $questionario->corso->numero_protocollo ?: $questionario->corso->nome }}</p>

@if($compilato)
    <div class="avviso-sola-lettura">Hai già compilato questo questionario. Grazie!</div>
@elseif(! $questionario->eAperto())
    <div class="avviso-sola-lettura">Il questionario è chiuso dal {{ $questionario->chiude_il->format('d/m/Y') }}: non si può più compilare.</div>
@else
    <div class="box">
        <p class="intro">{{ $introduzione }}</p>
        <p class="legenda">Scala: @foreach($scala as $v => $e){{ $v }} = {{ $e }}@if(! $loop->last) · @endif @endforeach</p>
        <p class="legenda">Tutte le risposte sono obbligatorie. Compila entro il {{ $questionario->chiude_il->format('d/m/Y') }}: l'invio è uno solo e non si può modificare.</p>
    </div>

    @if($errori)
        <div class="avviso-errore">Alcune risposte mancano o non sono valide: sono segnate in rosso.</div>
    @endif

    <form method="POST" action="{{ route('app.questionario.invia', $questionario) }}" id="questionario" novalidate>
        @csrf
        @foreach($sezioni as $sezione)
            @php
                $ambiti = $sezione['per_valutato'] ? array_keys($valutati) : [null];
            @endphp
            @foreach($ambiti as $i)
                <div class="box">
                    <p class="sez-titolo">{{ $i === null ? $sezione['titolo'] : $titoloValutato($valutati[$i]) }}</p>
                    @foreach($sezione['parti'] as $parte)
                    @if($parte['sottotitolo'])<p class="sez-sottotitolo">{{ $parte['sottotitolo'] }}</p>@endif
                    @foreach($parte['domande'] as $d)
                        @php
                            $nome   = $i === null ? "r[{$d['key']}]" : "r[{$d['key']}][{$i}]";
                            $campo  = $i === null ? $d['key'] : "{$d['key']}.{$i}";
                            $val    = $i === null ? ($vecchi[$d['key']] ?? null) : ($vecchi[$d['key']][$i] ?? null);
                            $label  = str_replace('{docente}', $i === null ? '' : $valutati[$i]['nome'], $d['label']);
                            $showIf = $d['show_if'] ? ($i === null ? "r[{$d['show_if'][0]}]" : "r[{$d['show_if'][0]}][{$i}]") . '|' . $d['show_if'][1] : null;
                        @endphp
                        <div class="domanda {{ isset($errori[$campo]) ? 'ha-errore' : '' }}" @if($showIf) data-show-if="{{ $showIf }}" @endif>
                            <p class="domanda-testo">{{ $label }}</p>
                            @switch($d['tipo'])
                                @case('scale5')
                                    <div class="scala" role="radiogroup" aria-label="{{ $label }}">
                                        @foreach($scala as $v => $e)
                                            <label class="opz"><input type="radio" name="{{ $nome }}" value="{{ $v }}" required @checked((string) $val === (string) $v)><span>{{ $v }}<small>{{ $e }}</small></span></label>
                                        @endforeach
                                    </div>
                                    @break
                                @case('single_choice')
                                @case('yes_no')
                                @case('single_choice_other')
                                    @php $opz = $d['tipo'] === 'yes_no' ? ['Sì', 'No'] : ($d['tipo'] === 'single_choice_other' ? [...$d['opzioni'], 'Altro'] : $d['opzioni']); @endphp
                                    <div class="opzioni" role="radiogroup" aria-label="{{ $label }}">
                                        @foreach($opz as $o)
                                            <label class="opz"><input type="radio" name="{{ $nome }}" value="{{ $o }}" required @checked($val === $o)><span>{{ $o }}</span></label>
                                        @endforeach
                                    </div>
                                    @if($d['tipo'] === 'single_choice_other')
                                        <div data-show-if="{{ $nome }}|Altro" style="margin-top:6px;">
                                            <input type="text" name="r[{{ $d['key'] }}_altro]" maxlength="{{ \App\Questionari\CompilazioneQuestionario::MAX_TESTO_BREVE }}" placeholder="Specifica" aria-label="Altro: specifica" required value="{{ $vecchi[$d['key'] . '_altro'] ?? '' }}">
                                        </div>
                                    @endif
                                    @break
                                @case('text_long')
                                    <textarea name="{{ $nome }}" maxlength="{{ \App\Questionari\CompilazioneQuestionario::MAX_TESTO_LUNGO }}" required aria-label="{{ $label }}">{{ is_string($val) ? $val : '' }}</textarea>
                                    @break
                                @case('text_short')
                                    <input type="text" name="{{ $nome }}" maxlength="{{ \App\Questionari\CompilazioneQuestionario::MAX_TESTO_BREVE }}" required aria-label="{{ $label }}" value="{{ is_string($val) ? $val : '' }}">
                                    @break
                            @endswitch
                            @isset($errori[$campo])<p class="errore">{{ $errori[$campo] }}</p>@endisset
                        </div>
                    @endforeach
                    @endforeach
                </div>
            @endforeach
        @endforeach

        <button type="submit" class="btn-invia" id="btn-invia">Invia il questionario</button>
        <p class="legenda" id="bozza-stato" aria-live="polite" style="font-weight:600;"></p>
        <p class="legenda">Le risposte si salvano da sole mentre compili: puoi chiudere e riprendere più tardi, anche da un altro dispositivo. La bozza è salvata temporaneamente e cifrata, finché non invii il questionario in forma completamente anonima.</p>
    </form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('questionario');
    if (!form) return;

    // Domande condizionali: visibili solo con la risposta indicata; nascoste = campi disattivati, non inviati.
    function aggiorna() {
        form.querySelectorAll('[data-show-if]').forEach(function (el) {
            const [nome, valore] = el.dataset.showIf.split('|');
            const scelto = form.querySelector('input[name="' + CSS.escape(nome) + '"]:checked');
            const visibile = !!scelto && scelto.value === valore;
            el.hidden = !visibile;
            el.querySelectorAll('input, textarea').forEach(function (c) { c.disabled = !visibile; });
        });
    }
    form.addEventListener('change', aggiorna);
    aggiorna();

    // Bozza: salvataggio automatico un secondo dopo l'ultima modifica; se l'app va in secondo piano con un
    // salvataggio in attesa, parte subito (sendBeacon arriva anche a pagina chiusa).
    const URL_BOZZA = @json(route('app.questionario.bozza', $questionario));
    const stato = document.getElementById('bozza-stato');
    let inviato = false;
    let attesa = null;
    function salvaBozza() {
        attesa = null;
        if (inviato) return;
        fetch(URL_BOZZA, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().then(function (j) { return { status: r.status, j: j }; }); })
            .then(function (x) {
                if (x.j.ok) stato.textContent = 'Bozza salvata alle ' + x.j.ora;
                else if (x.status === 409) stato.textContent = 'Il questionario è chiuso o già inviato: la bozza non si salva più.';
                else throw new Error();
            })
            .catch(function () { stato.textContent = 'Bozza non salvata: controlla la connessione, riprovo alla prossima modifica.'; });
    }
    function programma() { if (inviato) return; clearTimeout(attesa); attesa = setTimeout(salvaBozza, 1000); }
    form.addEventListener('input', programma);
    form.addEventListener('change', programma);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden' && attesa && !inviato) {
            clearTimeout(attesa); attesa = null;
            navigator.sendBeacon(URL_BOZZA, new FormData(form));
        }
    });

    // Un solo invio: se manca una risposta il browser porta alla prima; poi il pulsante si blocca.
    form.addEventListener('submit', function (e) {
        if (inviato || !form.checkValidity()) {
            if (!inviato) { e.preventDefault(); form.reportValidity(); }
            else e.preventDefault();
            return;
        }
        inviato = true;
        clearTimeout(attesa);
        document.getElementById('btn-invia').disabled = true;
    });
})();
</script>
@endpush
