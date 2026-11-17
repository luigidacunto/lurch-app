// Editor del messaggio anonimo nel portale badge (badge/portale.blade.php).
// Quill arriva da npm e viene incluso nel bundle di Vite: nessuna risorsa da CDN esterni.
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const quill = new Quill('#editor', {
    theme: 'snow',
    placeholder: 'Scrivi qui il tuo messaggio…',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline'],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            ['clean'],
        ],
    },
});

document.getElementById('form-messaggio').addEventListener('submit', function (e) {
    const testo = quill.getText().trim();
    if (!testo) {
        e.preventDefault();
        document.getElementById('error-vuoto').style.display = 'block';
        return;
    }
    document.getElementById('error-vuoto').style.display = 'none';
    document.getElementById('corpo-hidden').value = quill.root.innerHTML;
    document.getElementById('btn-invia').disabled = true;
    document.getElementById('btn-invia').textContent = 'Invio in corso…';
});
