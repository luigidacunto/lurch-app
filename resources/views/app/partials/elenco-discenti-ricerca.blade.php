{{-- Stili + ricerca client-side per gli elenchi di discenti. Le card hanno class="disc-card" e data-nome. --}}
@push('styles')
@include('app.partials.esami-stili')
<style>
    a.disc-card {
        display: block; color: inherit; text-decoration: none; min-height: 56px;
        background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 10px;
        border-left: 4px solid #C8102E; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    }
    a.disc-card[hidden] { display: none; }
    .cerca {
        width: 100%; min-height: 48px; padding: 0 14px; margin-bottom: 12px; box-sizing: border-box;
        border: 2px solid #e5e7eb; border-radius: 10px; font-size: 16px; background: #fff;
    }
    .disc-nome { font-size: 15px; font-weight: 700; color: #111827; overflow-wrap: anywhere; }
    .disc-stato { font-size: 13px; color: #6b7280; margin-top: 4px; }
</style>
@endpush
@push('scripts')
<script>
(function () {
    var campo = document.getElementById('cerca');
    if (!campo) return;
    var card = document.querySelectorAll('.disc-card');
    var vuoto = document.getElementById('nessun-risultato');
    // Minuscole e senza accenti; ogni parola scritta deve comparire nel nome.
    function norm(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    campo.addEventListener('input', function () {
        var parole = norm(campo.value).split(/\s+/).filter(Boolean);
        var visibili = 0;
        card.forEach(function (c) {
            var nome = norm(c.dataset.nome);
            var ok = parole.every(function (p) { return nome.indexOf(p) !== -1; });
            c.hidden = !ok;
            if (ok) visibili++;
        });
        vuoto.hidden = visibili > 0;
    });
})();
</script>
@endpush
