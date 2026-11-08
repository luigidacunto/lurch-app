{{-- Annotazione libera del docente sulla propria scheda (laboratorio, itinere). Parametri: $urlAnnotazione, $annotazione,
     $annotazioneMax, $modificabile, $chiLegge (chi altro la legge: diverso tra laboratorio e itinere). Si salva da sola
     (pausa di scrittura o uscita dal campo). --}}
@php $max = $annotazioneMax; @endphp
<style>
    .annotazione-box { background: #fff; border-radius: 12px; padding: 14px 16px; margin: 14px 0; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    .annotazione-box h3 { font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin: 0 0 4px; }
    .annotazione-box p { font-size: 12px; color: #6b7280; margin: 0 0 8px; }
    .annotazione-box textarea {
        width: 100%; min-height: 110px; box-sizing: border-box; padding: 10px 12px; font-size: 16px; font-family: inherit;
        border: 2px solid #e5e7eb; border-radius: 10px; resize: vertical;
    }
    .annotazione-box textarea:disabled { background: #f9fafb; }
    .annotazione-piede { display: flex; justify-content: space-between; font-size: 12px; color: #6b7280; margin-top: 4px; }
    .annotazione-piede .errore { color: #b91c1c; }
</style>
<div class="annotazione-box">
    <h3>Annotazione · facoltativa</h3>
    <p>Appunti tuoi su questo discente: non entrano nel calcolo né negli export; {{ $chiLegge }}.</p>
    <textarea id="annotazione" maxlength="{{ $max }}" placeholder="Scrivi qui i tuoi pensieri" @disabled(! $modificabile)>{{ $annotazione }}</textarea>
    <div class="annotazione-piede">
        <span id="annotazione-stato"></span>
        <span><span id="annotazione-conta">{{ mb_strlen((string) $annotazione) }}</span>/{{ $max }}</span>
    </div>
</div>

@if($modificabile)
@push('scripts')
<script>
(function () {
    var url = {!! json_encode($urlAnnotazione) !!};
    var token = {!! json_encode(csrf_token()) !!};
    var testo = document.getElementById('annotazione');
    var stato = document.getElementById('annotazione-stato');
    var conta = document.getElementById('annotazione-conta');
    var salvato = testo.value;
    var timer = null;

    function salva() {
        if (testo.value === salvato) return;
        var valore = testo.value;
        stato.className = '';
        stato.textContent = 'Salvo…';
        fetch(url, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify({annotazione: valore}),
            credentials: 'same-origin'
        }).then(function (r) {
            if (!r.ok) throw new Error(r.status);
            salvato = valore;
            stato.textContent = 'Salvata';
        }).catch(function () {
            stato.className = 'errore';
            stato.textContent = 'Non salvata — riprova';
        });
    }
    testo.addEventListener('input', function () {
        conta.textContent = testo.value.length;
        clearTimeout(timer);
        timer = setTimeout(salva, 800);
    });
    testo.addEventListener('blur', function () { clearTimeout(timer); salva(); });
})();
</script>
@endpush
@endif
