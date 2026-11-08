// ==UserScript==
// @name         LURCH - GAIA Bridge
// @namespace    __LURCH_URL__
// @version      1.11.2
// @description  Cerca automaticamente su GAIA l'ID profilo dei candidati di una graduatoria LURCH e lo salva in un click; importa le qualifiche CRI dal curriculum GAIA di un socio, di un intero comitato locale o di tutti i candidati di una graduatoria (analisi + avvio importazione, come l'import Excel)
// @match        __LURCH_URL__/admin/*
// @updateURL    __LURCH_URL__/tampermonkey/lurch-gaia-bridge.user.js
// @downloadURL  __LURCH_URL__/tampermonkey/lurch-gaia-bridge.user.js
// @grant        GM_xmlhttpRequest
// @connect      gaia.cri.it
// @run-at       document-idle
// ==/UserScript==

(function () {
    'use strict';

    // Convenzione "Colori pulsanti per
    // funzione": questi due sono i colori "liberi" assegnati alle azioni eseguite da questo
    // stesso userscript (non badge di stato, quelli restano ai 5 riservati globalmente).
    // - BTN_PRIMARY (rosa brand): operazione eseguita subito, non distruttiva, non salva ancora
    //   su LURCH (es. "Cerca ID GAIA", "Leggi curriculum GAIA").
    // - BTN_SUCCESS (verde, riservato globalmente): salvataggio permanente dei dati (es. "Avvia
    //   importazione").
    const BTN_PRIMARY = 'padding:8px 16px;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:#fff;background:#a72863;';
    const BTN_SUCCESS = 'padding:8px 16px;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:#fff;background:#16a34a;';
    const BTN_SECONDARY = 'padding:8px 16px;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151;background:#fff;';

    const GAIA_AUTOCOMPLETE_URL = 'https://gaia.cri.it/autocomplete/PersonaAutocompletamento/';
    const BTN_ID = 'lurch-gaia-bridge-btn';
    // Id univoco cercato nel DOM: il bottone "Installa userscript" lato LURCH (Filament) porta
    // lo stesso attributo data-userscript-install="..." — se lo troviamo, lo script è
    // installato e attivo, quindi lo sostituiamo con il/i bottone/i funzionante/i invece di
    // lasciare visibile il link di download. Convenzione pensata per essere riusata: futuri
    // bottoni "Installa" in altre sezioni useranno un valore diverso in questo stesso attributo.
    // Pagina Candidati graduatoria (ricerca ID + import batch): un solo bottone "Installa" lato
    // Filament, sostituito con ENTRAMBI i bottoni funzionanti — vedi aggiungiPulsantiPaginaCandidati().
    const HUB_INSTALL_SELECTOR = '[data-userscript-install="lurch-gaia-graduatoria-hub"]';

    function log(...args) {
        console.log('LURCH-GAIA Bridge:', ...args);
    }

    // Il cookie XSRF-TOKEN di Laravel non è HttpOnly per costruzione (deve poter essere letto
    // da JS e rimandato indietro come header X-XSRF-TOKEN — Laravel lo decripta lui stesso in
    // VerifyCsrfToken). Nessun meccanismo custom: stessa cosa che fa già axios di default.
    function leggiCookie(nome) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + nome + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function idGraduatoriaDaUrl() {
        const match = window.location.pathname.match(/\/graduatorie\/(\d+)\/candidati/);
        return match ? match[1] : null;
    }

    // @include è generico su tutto /admin/* (un solo pattern per dominio, non uno per ogni
    // pagina): ogni funzionalità si attiva solo se l'URL corrente corrisponde al proprio
    // pattern specifico, controllato qui in JS invece che nell'header dello userscript.
    function paginaSocioDaUrl() {
        const match = window.location.pathname.match(/\/admin\/soci\/(\d+)$/);
        return match ? match[1] : null;
    }

    function paginaQualificheSociDaUrl() {
        return window.location.pathname === '/admin/qualifiche-soci';
    }

    function cercaSuGaia(cf) {
        return new Promise((resolve) => {
            GM_xmlhttpRequest({
                method: 'GET',
                url: GAIA_AUTOCOMPLETE_URL + '?q=' + encodeURIComponent(cf),
                onload: (res) => resolve({ ok: res.status === 200, status: res.status, body: res.responseText }),
                onerror: () => resolve({ ok: false, status: 0, body: '' }),
                ontimeout: () => resolve({ ok: false, status: 0, body: '' }),
            });
        });
    }

    function estraiIdProfilo(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const span = doc.querySelector('span[data-value]');
        return span ? span.getAttribute('data-value') : null;
    }

    function attesa(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    // Pausa 2-5s tra una richiesta GAIA e l'altra nel loop batch multi-socio (vedi
    // eseguiRaccoltaBatch): va applicata DOPO aver ricevuto la risposta precedente, non sommata
    // al tempo di risposta di GAIA — deliberatamente più larga dell'attesa(200) usata dal loop
    // ricerca-ID candidati (quel loop fa solo richieste leggere di autocomplete, questo aggiunge
    // anche la lettura del curriculum completo per ogni socio).
    function pausaCasuale() {
        return attesa(2000 + Math.random() * 3000);
    }

    // URL relativa (stesso dominio da cui gira lo script: prod, 127.0.0.1 o localhost) — legge
    // il file .user.js pubblicato con una fetch same-origin (nessun CORS, non serve
    // GM_xmlhttpRequest qui) e ne estrae la @version dall'header. Fonte unica: nessun numero di
    // versione duplicato lato PHP, basta bump-are l'header di questo stesso file.
    // Cache in-memory (non cambia durante la sessione): gestisciBottoneInstalla() viene
    // richiamata ad ogni mutazione DOM osservata sulla pagina socio (vedi
    // aggiungiPulsantiPaginaSocio) finché il bottone resta in stato "disallineato", altrimenti
    // ogni mutazione rifarebbe una fetch identica.
    let _versionePubblicataCache;
    async function versionePubblicata() {
        if (_versionePubblicataCache !== undefined) return _versionePubblicataCache;
        try {
            // cache:'no-store' + query di cache-busting: senza questo il browser (o una cache
            // intermedia) può servire una copia di questo stesso file precedente al deploy,
            // facendo risultare "installata === pubblicata" anche quando non è più vero.
            const resp = await fetch('/tampermonkey/lurch-gaia-bridge.user.js?_=' + Date.now(), {
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!resp.ok) return (_versionePubblicataCache = null);
            const match = (await resp.text()).match(/@version\s+([\d.]+)/);
            return (_versionePubblicataCache = match ? match[1] : null);
        } catch {
            return (_versionePubblicataCache = null);
        }
    }

    // Meccanismo condiviso da ogni bottone "Installa" generato lato Filament
    // (data-userscript-install="..."): se la versione installata (GM_info.script.version) è
    // diversa da quella pubblicata, non sostituisce il bottone ma lo rietichetta "Aggiorna
    // userscript" (stesso link di download, che Tampermonkey riconosce come update disponibile).
    // Se le versioni combaciano (o la verifica fallisce, es. offline: fail-open) sostituisce il
    // bottone con quello prodotto da creaBottoneFn(installBtn). Riusabile per qualsiasi futuro
    // bottone generato dallo userscript, non solo i due attuali.
    // Rimuove href e rende non interagibile un link "Installa" ancora presente nel DOM, per
    // coprire la finestra asincrona del controllo versione (vedi gestisciBottoneInstalla).
    // Ritorna l'href originale, da ripristinare con riattivaConHref() se si finisce nel ramo
    // "Aggiorna" (dove il link deve tornare cliccabile verso il file .user.js).
    function disattivaTemporaneamente(installBtn) {
        const hrefOriginale = installBtn.getAttribute('href');
        installBtn.removeAttribute('href');
        installBtn.style.pointerEvents = 'none';
        installBtn.style.opacity = '0.5';
        return hrefOriginale;
    }

    function riattivaConHref(installBtn, hrefOriginale) {
        if (hrefOriginale) installBtn.setAttribute('href', hrefOriginale);
        installBtn.style.pointerEvents = '';
        installBtn.style.opacity = '';
    }

    async function gestisciBottoneInstalla(installBtn, creaBottoneFn) {
        // Race con il click reale: tra la comparsa del link "Installa" nel DOM (Livewire,
        // pannelli lazy) e la sua sostituzione c'è un salto asincrono (fetch di
        // versionePubblicata()). In quella finestra il link resta un <a href=".../user.js"
        // target="_blank"> cliccabile con il suo href originale — un click in quel momento
        // naviga davvero lì e Tampermonkey mostra la sua schermata nativa di installazione/
        // reinstallazione, scavalcando questo stesso meccanismo. Disattivato subito,
        // sincrono, prima di qualunque await.
        const hrefOriginale = disattivaTemporaneamente(installBtn);

        const latest = await versionePubblicata();
        const installata = typeof GM_info !== 'undefined' ? GM_info.script.version : null;

        if (latest && installata && installata !== latest) {
            riattivaConHref(installBtn, hrefOriginale);
            installBtn.textContent = `Aggiorna userscript (installata v${installata} → v${latest})`;
            log('Versione installata diversa da quella pubblicata: mostro "Aggiorna" invece di sostituire il bottone.');
            return null;
        }

        // Il colore finale lo decide creaBottoneFn in base alla funzione del bottone prodotto
        // — non erediamo qui la classe Filament del link
        // "Installa", altrimenti un bottone che esegue un'operazione (rosa) o salva dati
        // (verde) finirebbe col colore "apre risorsa" (blu) del link che sostituisce.
        const $btn = creaBottoneFn(installBtn);
        installBtn.replaceWith($btn);
        return $btn;
    }

    async function eseguiRicerca($btn) {
        const idGraduatoria = idGraduatoriaDaUrl();
        if (!idGraduatoria) {
            alert('Pagina non riconosciuta (URL graduatoria non trovato).');
            return;
        }

        const testoOriginale = $btn.textContent;
        $btn.disabled = true;
        $btn.textContent = 'Leggo elenco candidati…';

        let candidati;
        try {
            const resp = await fetch(`/api/v1/graduatorie/${idGraduatoria}/gaia-persona-id/da-cercare`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            candidati = await resp.json();
        } catch (err) {
            alert("Impossibile leggere l'elenco candidati da LURCH: " + err.message);
            $btn.disabled = false;
            $btn.textContent = testoOriginale;
            return;
        }

        if (candidati.length === 0) {
            alert('Nessun candidato da cercare: hanno già tutti un ID GAIA (o nessun socio agganciato).');
            $btn.disabled = false;
            $btn.textContent = testoOriginale;
            return;
        }

        // Una sola chiamata di prova prima di lanciarsi su tutto l'elenco: se la sessione GAIA
        // non è attiva evitiamo N richieste inutili (e inutilmente rumorose sul server GAIA).
        $btn.textContent = 'Verifico sessione GAIA…';
        const prova = await cercaSuGaia(candidati[0].cf);
        if (!prova.ok) {
            alert(
                'GAIA non raggiungibile o sessione scaduta (status ' + prova.status + ').\n'
                + 'Verifica di essere loggato su gaia.cri.it in questo stesso browser, poi riprova.'
            );
            $btn.disabled = false;
            $btn.textContent = testoOriginale;
            return;
        }

        // La chiamata di prova sopra copre solo "sessione già scaduta prima di iniziare" — questa
        // protezione copre invece il caso in cui scade A METÀ del giro (stesso principio di
        // eseguiRaccoltaBatchInterna). "Nessun ID trovato" resta un esito normale (non tutti i
        // candidati sono necessariamente su GAIA) e non conta come fallimento: solo un vero errore
        // HTTP (esito.ok === false) è un segnale di sessione scaduta.
        const MAX_FALLIMENTI_CONSECUTIVI = 3;
        let fallimentiConsecutivi = 0;
        let interrotto = false;

        const risultati = [];
        for (let i = 0; i < candidati.length; i++) {
            const candidato = candidati[i];
            $btn.textContent = `Ricerca ${i + 1}/${candidati.length}…`;

            const esito = i === 0 ? prova : await cercaSuGaia(candidato.cf);
            if (esito.ok) {
                fallimentiConsecutivi = 0;
                const idProfilo = estraiIdProfilo(esito.body);
                if (idProfilo) {
                    risultati.push({ socio_id: candidato.socio_id, gaia_persona_id: idProfilo });
                }
            } else {
                fallimentiConsecutivi++;
                log('Ricerca fallita per CF', candidato.cf, 'status', esito.status);
                if (fallimentiConsecutivi >= MAX_FALLIMENTI_CONSECUTIVI) {
                    interrotto = true;
                    break;
                }
            }

            await attesa(200);
        }

        if (interrotto) {
            alert(
                `Ricerca interrotta dopo ${MAX_FALLIMENTI_CONSECUTIVI} errori consecutivi `
                + '(sessione GAIA probabilmente scaduta — verifica di essere loggato su gaia.cri.it, poi riprova). '
                + `${risultati.length} ID trovati sui candidati già processati.`
            );
            if (risultati.length === 0) {
                $btn.disabled = false;
                $btn.textContent = testoOriginale;
                return;
            }
        }

        if (risultati.length === 0) {
            alert('Ricerca completata: nessun ID GAIA trovato per i ' + candidati.length + ' candidati.');
            $btn.disabled = false;
            $btn.textContent = testoOriginale;
            return;
        }

        $btn.textContent = 'Salvo su LURCH…';
        try {
            const resp = await fetch('/api/v1/soci/gaia-persona-id', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': leggiCookie('XSRF-TOKEN'),
                },
                body: JSON.stringify({ risultati }),
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const data = await resp.json();
            alert(`Trovati e salvati ${data.aggiornati} ID GAIA su ${candidati.length} candidati cercati.`);
            window.location.reload();
        } catch (err) {
            alert(
                'Ricerca completata (' + risultati.length + ' trovati) ma il salvataggio su LURCH è fallito: '
                + err.message
            );
            $btn.disabled = false;
            $btn.textContent = testoOriginale;
        }
    }

    function creaBottoneRicerca() {
        const $btn = document.createElement('button');
        $btn.id = BTN_ID;
        $btn.type = 'button';
        $btn.textContent = 'Cerca ID GAIA';
        $btn.style.cssText = BTN_PRIMARY; // esegue subito l'operazione di ricerca
        $btn.addEventListener('click', () => eseguiRicerca($btn));
        return $btn;
    }

    // Pagina Candidati: un solo bottone "Installa" lato Filament (data-userscript-install=
    // "lurch-gaia-graduatoria-hub", vedi GestisciCandidati::installaUserscriptGaiaAction()) —
    // se la versione combacia, gestisciBottoneInstalla() lo sostituisce con un wrapper che
    // contiene i DUE bottoni funzionanti (ricerca ID + import batch); se non combacia diventa
    // "Aggiorna" (un solo messaggio, non uno a testa — comportamento di gestisciBottoneInstalla
    // invariato). Prima della v1.10.11 erano due Action/due link "Installa" separati per lo
    // stesso file: se lo userscript non era ancora attivo comparivano due inviti ridondanti a
    // scaricare la stessa cosa.
    function creaBottoniPaginaCandidati(installBtn) {
        const $wrapper = document.createElement('span');
        $wrapper.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;';
        $wrapper.append(creaBottoneRicerca(), creaBottoneBatchGraduatoria(installBtn));
        return $wrapper;
    }

    function aggiungiPulsantiPaginaCandidati() {
        if (document.getElementById(BTN_ID) && document.getElementById(BATCH_GRADUATORIA_BTN_ID)) return;

        const installBtn = document.querySelector(HUB_INSTALL_SELECTOR);
        if (!installBtn) return;

        gestisciBottoneInstalla(installBtn, creaBottoniPaginaCandidati);
    }

    // ------------------------------------------------------------------------
    // Lettura curriculum GAIA (scheda socio) — solo confronto, nessun salvataggio.
    // ------------------------------------------------------------------------

    const CURRICULUM_BTN_ID = 'lurch-gaia-curriculum-btn';
    // Pagina Socio (ricerca ID + import CV): un solo bottone "Installa" lato Filament, sostituito
    // con ENTRAMBI i bottoni funzionanti — vedi creaBottoniPaginaSocio()/aggiungiPulsantiPaginaSocio().
    const SOCIO_HUB_INSTALL_SELECTOR = '[data-userscript-install="lurch-gaia-socio-hub"]';

    // Sessione GAIA scaduta => risposta status 200 ma redirect (silenzioso, lato server) alla
    // pagina di login invece del curriculum richiesto — uno status 200 da solo non basta a
    // distinguerla da una lettura riuscita. finalUrl (fornito da GM_xmlhttpRequest dopo aver
    // seguito eventuali redirect) va quindi confrontato con l'URL richiesto: se non coincidono
    // più, la sessione non è valida. Prima di questo fix la risposta di login veniva accettata
    // come "ok", il curriculum estratto non conteneva righe e il loop di eseguiRaccoltaBatchInterna
    // proseguiva silenziosamente fino in fondo alla lista senza mai contare l'errore (bug reale,
    // 2026-08-13: "importa qualifiche" non si fermava più dopo N fallimenti consecutivi a sessione
    // scaduta, perché per i soci con gaia_persona_id già noto questa era l'unica chiamata fatta).
    function leggiCurriculum(gaiaPersonaId) {
        const url = `https://gaia.cri.it/profilo/${gaiaPersonaId}/curriculum/`;
        return new Promise((resolve) => {
            GM_xmlhttpRequest({
                method: 'GET',
                url,
                onload: (res) => resolve({
                    ok: res.status === 200 && (!res.finalUrl || res.finalUrl.includes(`/profilo/${gaiaPersonaId}/curriculum/`)),
                    status: res.status,
                    body: res.responseText,
                }),
                onerror: () => resolve({ ok: false, status: 0, body: '' }),
                ontimeout: () => resolve({ ok: false, status: 0, body: '' }),
            });
        });
    }

    // Solo righe "Qualifica CRI" (esclude Patente CRI/Civile, Altra Qualifica, Titolo di
    // Studio) e solo quelle valide: "Confermato" (il caso normale) oppure "Certificato" —
    // quest'ultimo è lo stato delle qualifiche per equipollenza automatica (es. "Modulo
    // Trasversale Trainer CRI" assegnato da GAIA senza passare per una conferma manuale:
    // stesso span.text-success, testo diverso).
    // Qualsiasi altro stato o l'assenza dello span viene scartato, per stare sul sicuro finché
    // non conosciamo la dicitura esatta di "in attesa"/"rifiutata" usata da GAIA. Raggruppa per
    // nome corso normalizzato, raccogliendo tutte le date trovate (come parseCellaQualifiche()
    // nel parser Excel): serve per distinguere un inserimento (1 data) da un rinnovo (più
    // date), stessa logica che analizzaCorsoSingolo() applica lato server.
    //
    // Una riga "Qualifica CRI" valida (Confermato/Certificato) di solito ha comunque una data
    // di assegnazione, ma non è garantito (equipollenze storiche più vecchie, dati incompleti
    // su GAIA) — in quel caso non va scartata silenziosamente: nome_corso senza data viene
    // restituito a parte in `senzaData`, per essere mostrato come riga di errore nel report
    // dell'analisi invece di sparire senza traccia.
    function estraiQualificheCriConfermate(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const gruppi = new Map();
        const senzaData = [];

        doc.querySelectorAll('table tbody tr').forEach((tr) => {
            const tds = tr.querySelectorAll('td');
            if (tds.length < 3) return;
            if (tds[0].textContent.trim() !== 'Qualifica CRI') return;

            const valida = Array.from(tds[1].querySelectorAll('span.text-success'))
                .some((span) => span.textContent.includes('Confermato') || span.textContent.includes('Certificato'));
            if (!valida) return;

            const nome = tds[1].querySelector('p.grassetto')?.textContent.trim();
            if (!nome) return;

            const dataMatch = tds[2].textContent.match(/(\d{2})\/(\d{2})\/(\d{4})/);
            if (!dataMatch) {
                senzaData.push(nome);
                return;
            }

            const data = `${dataMatch[3]}-${dataMatch[2]}-${dataMatch[1]}`;
            const chiave = nome.toLowerCase().trim();
            if (!gruppi.has(chiave)) gruppi.set(chiave, { nome_corso: nome, dates: new Set() });
            gruppi.get(chiave).dates.add(data);
        });

        return {
            corsi: Array.from(gruppi.values()).map((g) => ({ nome_corso: g.nome_corso, dates: Array.from(g.dates) })),
            senzaData,
        };
    }

    // Riga sintetica, mai mandata al server (analizzaCorsoSingolo si aspetta sempre almeno una
    // data): stessa forma di una riga normale così mostraRisultatoCurriculum() non ha bisogno
    // di logica dedicata. `extra` porta socio_id/cognome/nome solo nella modale batch
    // (mostraSocio true).
    function creaRigaErroreDataMancante(nomeCorso, extra = {}) {
        return {
            qualifica_nome_raw: nomeCorso,
            stato: 'data_mancante',
            errore: 'Qualifica valida su GAIA ma senza una data riconoscibile — riga saltata, verificare manualmente.',
            ...extra,
        };
    }

    // Righe "Patente CRI" del curriculum (esclude "Patente Civile"), solo stato valido
    // (Confermato/Certificato). Il nome è del tipo "PATENTE CRI 5B" — la tipologia grezza
    // (numerazione modelli 138/05 e 138/19) viene mandata al server, che la converte al modello
    // 2024 (vedi PatenteCriGaiaConverter lato PHP). Da td[2] si estraggono data di rilascio
    // (icona calendar), data di scadenza (hourglass-end) e protocollo (barcode + span.monospace)
    // via regex sull'innerHTML — più robusto del walk sui text node, che qui sono sciolti tra
    // <br> e <i>.
    function estraiPatentiCriConfermate(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const patenti = [];

        doc.querySelectorAll('table tbody tr').forEach((tr) => {
            const tds = tr.querySelectorAll('td');
            if (tds.length < 3) return;
            if (tds[0].textContent.trim() !== 'Patente CRI') return;

            const valida = Array.from(tds[1].querySelectorAll('span.text-success'))
                .some((span) => span.textContent.includes('Confermato') || span.textContent.includes('Certificato'));
            if (!valida) return;

            const nome = tds[1].querySelector('p.grassetto')?.textContent.trim();
            if (!nome) return;
            const tipologiaRaw = nome.replace(/^patente\s+cri\s+/i, '').trim();
            if (!tipologiaRaw) return;

            const html2 = tds[2].innerHTML;
            const isoData = (re) => {
                const m = html2.match(re);
                return m ? `${m[3]}-${m[2]}-${m[1]}` : null;
            };

            patenti.push({
                tipologia_raw: tipologiaRaw,
                data_rilascio: isoData(/fa-calendar[^>]*><\/i>\s*(\d{2})\/(\d{2})\/(\d{4})/),
                data_scadenza: isoData(/fa-hourglass-end[^>]*><\/i>\s*(\d{2})\/(\d{2})\/(\d{4})/),
                protocollo: (html2.match(/fa-barcode[^>]*><\/i>\s*<span[^>]*>([^<]+)<\/span>/) || [])[1]?.trim() || null,
            });
        });

        return patenti;
    }

    const MODAL_ID = 'lurch-gaia-curriculum-modal';

    // Stessi stati prodotti da QualificheSociImportParser::analizzaCorso() (vedi
    // app/Services/QualificheSociImportParser.php) e stesso linguaggio/colori della tabella di
    // anteprima import Excel in list-qualifiche-soci.blade.php (badge "Nuovo"/"Aggiorna"/"Già
    // aggiornato"/"Qual. mancante") — qui in inline-style perché questa pagina non ha
    // Tailwind/CSS di Filament disponibile.
    const STATI_BADGE = {
        inserimento:      { label: 'Nuovo',           bg: '#dcfce7', border: '#16a34a', color: '#166534' },
        aggiornamento:    { label: 'Aggiorna',         bg: '#dbeafe', border: '#2563eb', color: '#1e40af' },
        obsoleta:         { label: 'Già aggiornato',  bg: '#f1f5f9', border: '#cbd5e1', color: '#64748b' },
        saltata:          { label: 'Saltata',         bg: '#f3f4f6', border: '#d1d5db', color: '#6b7280' },
        errore_qualifica: { label: 'Non in catalogo', bg: '#fef3c7', border: '#d97706', color: '#92400e' },
        // Qualifica GAIA/Excel già in Lurch ma non più trovata in questa lettura del curriculum
        // (vedi QualificheSociImportParser::trovaQualificheRimosse() lato server) — verrà
        // eliminata da Lurch se l'importazione viene confermata.
        rimossa:          { label: 'Rimossa su GAIA', bg: '#fee2e2', border: '#dc2626', color: '#991b1b' },
        // Riga "Qualifica CRI" valida (Confermato/Certificato) ma senza data riconoscibile —
        // mai inviata al server, generata solo lato client (vedi creaRigaErroreDataMancante()).
        data_mancante:    { label: 'Data mancante',   bg: '#fee2e2', border: '#dc2626', color: '#991b1b' },
        // Righe "Patente CRI" (vedi PatenteCriGaiaImport lato PHP) — stessi colori semantici
        // delle qualifiche, prefisso patente_ per non collidere.
        patente_inserimento:      { label: 'Patente — Nuova',        bg: '#dcfce7', border: '#16a34a', color: '#166534' },
        patente_aggiornamento:    { label: 'Patente — Aggiorna',     bg: '#dbeafe', border: '#2563eb', color: '#1e40af' },
        patente_obsoleta:         { label: 'Patente — Già presente', bg: '#f1f5f9', border: '#cbd5e1', color: '#64748b' },
        patente_non_convertibile: { label: 'Patente — Non convertibile', bg: '#fee2e2', border: '#dc2626', color: '#991b1b' },
        patente_rimossa:          { label: 'Patente — Da rimuovere', bg: '#fee2e2', border: '#dc2626', color: '#991b1b' },
    };

    const FILTRI = [
        { key: 'tutti',         label: 'Totali',          match: () => true },
        { key: 'inserimento',   label: 'Nuovi',           match: (s) => s === 'inserimento' },
        { key: 'aggiornamento', label: 'Aggiornati',      match: (s) => s === 'aggiornamento' },
        { key: 'obsoleta',      label: 'Già aggiornati',  match: (s) => s === 'obsoleta' },
        { key: 'rimosse',       label: 'Da rimuovere',    match: (s) => s === 'rimossa' || s === 'patente_rimossa' },
        { key: 'patenti',       label: 'Patenti',         match: (s) => typeof s === 'string' && s.startsWith('patente_') },
        { key: 'errori',        label: 'Errori',          match: (s) => s === 'errore_qualifica' || s === 'saltata' || s === 'data_mancante' || s === 'patente_non_convertibile' },
    ];

    // Riepilogo testuale condiviso tra modale singolo-socio e batch: separa il conteggio
    // qualifiche da quello patenti (righe con tipo_riga === 'patente') così l'operatore vede a
    // colpo d'occhio quante ne sono state trovate per ciascuna categoria.
    // `prefisso` = testo iniziale opzionale (es. "N soci processati");
    // `senzaData` = righe qualifica valide ma senza data (sintetiche, solo lato client).
    // Ritorna anche `haAzioni` per decidere se mostrare il bottone "Avvia importazione".
    function costruisciRiepilogo(righe, { prefisso = '', senzaData = 0 } = {}) {
        const q = righe.filter((r) => (r.tipo_riga || null) !== 'patente' && r.stato !== 'data_mancante');
        const p = righe.filter((r) => r.tipo_riga === 'patente');

        const qRimosse = q.filter((r) => r.stato === 'rimossa').length;
        const qAvviabili = q.filter((r) => ['inserimento', 'aggiornamento', 'obsoleta'].includes(r.stato)).length;
        const qNonCat = q.filter((r) => r.stato === 'errore_qualifica').length;

        const pAvviabili = p.filter((r) => r.stato === 'patente_inserimento' || r.stato === 'patente_aggiornamento').length;
        const pRimosse = p.filter((r) => r.stato === 'patente_rimossa').length;
        const pNonConv = p.filter((r) => r.stato === 'patente_non_convertibile').length;

        const testo = (prefisso ? prefisso + ' — ' : '')
            + `${q.length - qRimosse} qualifiche trovate`
            + (qAvviabili > 0 ? `, ${qAvviabili} da importare/ricalcolare` : '')
            + (qRimosse > 0 ? `, ${qRimosse} da rimuovere (non più su GAIA)` : '')
            + (qNonCat > 0 ? `, ${qNonCat} non in catalogo (saltate)` : '')
            + (senzaData > 0 ? `, ${senzaData} senza data (saltate)` : '')
            + `. ${p.length - pRimosse} patenti trovate`
            + (pAvviabili > 0 ? `, ${pAvviabili} da importare` : '')
            + (pRimosse > 0 ? `, ${pRimosse} da rimuovere` : '')
            + (pNonConv > 0 ? `, ${pNonConv} non convertibili al modello 2024 (saltate)` : '')
            + '.';

        return { testo, haAzioni: qAvviabili > 0 || qRimosse > 0 || pAvviabili > 0 || pRimosse > 0 };
    }

    // Dialog nativo del browser: niente libreria di modali, gestisce già backdrop e chiusura
    // con Esc/click esterno. Il centraggio automatico di <dialog> (margin:auto da UA
    // stylesheet) viene però annullato dal reset globale di Tailwind/Filament (margin:0 su
    // tutti gli elementi, un foglio stile "author" che nella cascata CSS vince sempre su
    // quello "user agent") — va quindi forzato esplicitamente con position:fixed + transform,
    // altrimenti il dialog resta ancorato in alto a sinistra. Creata una sola volta, riusata ad
    // ogni apertura; lo stato dell'analisi in corso vive su proprietà del dialog stesso
    // ($dialog._righeAnalisi/_filtro), niente stato globale.
    function creaModaleCurriculum() {
        const $dialog = document.createElement('dialog');
        $dialog.id = MODAL_ID;
        $dialog.style.cssText = [
            'padding:0', 'border:none', 'border-radius:8px',
            'max-width:1100px', 'width:95vw', 'max-height:90vh',
            'position:fixed', 'top:50%', 'left:50%', 'transform:translate(-50%,-50%)', 'margin:0',
        ].join(';');
        $dialog.innerHTML = `
            <div style="display:flex;flex-direction:column;max-height:90vh;">
                <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;font-size:16px;">Import qualifiche CRI da curriculum GAIA</h3>
                    <button type="button" data-ruolo="chiudi" style="border:none;background:none;font-size:18px;cursor:pointer;line-height:1;">✕</button>
                </div>
                <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <button type="button" data-ruolo="esegui" style="${BTN_PRIMARY}">Leggi curriculum GAIA</button>
                    <button type="button" data-ruolo="conferma" style="${BTN_SUCCESS};display:none;">Avvia importazione</button>
                    <span data-ruolo="riepilogo" style="font-size:13px;color:#6b7280;"></span>
                </div>
                <div data-ruolo="filtri-ricerca" style="padding:10px 20px;border-bottom:1px solid #e5e7eb;display:none;">
                    <div data-ruolo="filtri" style="margin-bottom:8px;"></div>
                    <input type="text" data-ruolo="ricerca" placeholder="Cerca per nome corso..."
                        style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                </div>
                <div data-ruolo="risultato" style="padding:16px 20px;overflow:auto;flex:1;font-size:13px;"></div>
            </div>
        `;
        $dialog.querySelector('[data-ruolo="chiudi"]').addEventListener('click', () => $dialog.close());
        $dialog.querySelector('[data-ruolo="ricerca"]').addEventListener('input', (e) => {
            $dialog._ricerca = e.target.value.trim().toLowerCase();
            renderTabellaFiltrata($dialog);
        });
        document.body.appendChild($dialog);
        return $dialog;
    }

    function renderFiltri($dialog) {
        $dialog.querySelector('[data-ruolo="filtri-ricerca"]').style.display = 'block';
        const $filtri = $dialog.querySelector('[data-ruolo="filtri"]');
        $filtri.innerHTML = FILTRI.map((f) => {
            const n = $dialog._righeAnalisi.filter((r) => f.match(r.stato)).length;
            const attivo = $dialog._filtro === f.key;
            return `<button type="button" data-filtro="${f.key}" style="padding:4px 10px;margin-right:6px;border-radius:14px;border:1px solid ${attivo ? '#374151' : '#d1d5db'};background:${attivo ? '#374151' : '#fff'};color:${attivo ? '#fff' : '#374151'};cursor:pointer;font-size:12px;">${f.label} (${n})</button>`;
        }).join('');
        $filtri.querySelectorAll('[data-filtro]').forEach(($b) => {
            $b.addEventListener('click', () => {
                $dialog._filtro = $b.dataset.filtro;
                renderFiltri($dialog);
                renderTabellaFiltrata($dialog);
            });
        });
    }

    // mostraSocio: colonna aggiuntiva "Socio" usata solo dalla modale batch multi-comitato
    // (creaModaleBatchMultiSocio) — la modale singolo-socio non la passa mai (undefined/false).
    function mostraRisultatoCurriculum($risultato, righe, mostraSocio) {
        if (righe.length === 0) {
            $risultato.innerHTML = '<p style="color:#6b7280;">Nessuna riga in questa categoria.</p>';
            return;
        }

        const colonnaSocio = mostraSocio ? '<th style="text-align:left;padding:6px 8px;">Socio</th>' : '';
        const rows = righe.map((r) => {
            const badge = STATI_BADGE[r.stato] || STATI_BADGE.saltata;
            const date = [r.data_acquisizione, r.data_ultimo_rinnovo].filter(Boolean).join(' → ');
            const cellaSocio = mostraSocio
                ? `<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;white-space:nowrap;"><a href="/admin/soci/${r.socio_id}" target="_blank" rel="noopener">${r.cognome ?? ''} ${r.nome ?? ''}</a></td>`
                : '';
            return `
                <tr>
                    ${cellaSocio}
                    <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">${r.qualifica_nome_raw ?? ''}</td>
                    <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;white-space:nowrap;">${date || '—'}</td>
                    <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">
                        <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;background:${badge.bg};color:${badge.color};border:1px solid ${badge.border};">${badge.label}</span>
                    </td>
                    <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;color:#6b7280;">${r.errore ?? ''}</td>
                </tr>
            `;
        }).join('');

        $risultato.innerHTML = `
            <table style="border-collapse:collapse;width:100%;">
                <thead>
                    <tr>
                        ${colonnaSocio}
                        <th style="text-align:left;padding:6px 8px;">Corso (GAIA)</th>
                        <th style="text-align:left;padding:6px 8px;">Date</th>
                        <th style="text-align:left;padding:6px 8px;">Stato</th>
                        <th style="text-align:left;padding:6px 8px;">Note</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    }

    // Filtro per stato (tab) + ricerca libera per nome corso (come il campo di ricerca
    // dell'anteprima import Excel): solo visualizzazione, non cambia cosa viene importato —
    // eseguiImportazione()/eseguiImportazioneBatch() mandano sempre tutte le righe analizzate,
    // indipendentemente da quali sono visibili in questo momento. Nella modale batch
    // ($dialog._mostraSocio true) la ricerca copre anche cognome/nome, non solo il corso.
    function renderTabellaFiltrata($dialog) {
        const filtro = FILTRI.find((f) => f.key === $dialog._filtro) || FILTRI[0];
        const ricerca = $dialog._ricerca || '';
        const mostraSocio = !!$dialog._mostraSocio;
        mostraRisultatoCurriculum(
            $dialog.querySelector('[data-ruolo="risultato"]'),
            $dialog._righeAnalisi.filter((r) => {
                if (!filtro.match(r.stato)) return false;
                if (!ricerca) return true;
                const testo = mostraSocio
                    ? `${r.qualifica_nome_raw ?? ''} ${r.cognome ?? ''} ${r.nome ?? ''}`
                    : (r.qualifica_nome_raw ?? '');
                return testo.toLowerCase().includes(ricerca);
            }),
            mostraSocio
        );
    }

    async function eseguiLetturaCurriculum($dialog, gaiaPersonaId, socioId) {
        const $eseguiBtn = $dialog.querySelector('[data-ruolo="esegui"]');
        const $confermaBtn = $dialog.querySelector('[data-ruolo="conferma"]');
        const $riepilogo = $dialog.querySelector('[data-ruolo="riepilogo"]');
        const $risultato = $dialog.querySelector('[data-ruolo="risultato"]');

        const testoOriginale = $eseguiBtn.textContent;
        $eseguiBtn.disabled = true;
        $eseguiBtn.textContent = 'Leggo curriculum GAIA…';
        $confermaBtn.style.display = 'none';
        $riepilogo.textContent = '';
        $risultato.innerHTML = '';
        $dialog.querySelector('[data-ruolo="filtri-ricerca"]').style.display = 'none';

        const esito = await leggiCurriculum(gaiaPersonaId);
        if (!esito.ok) {
            $risultato.innerHTML = `<p>Impossibile leggere il curriculum GAIA (status ${esito.status}). Verifica di essere loggato su gaia.cri.it in questo browser e di avere i permessi su questo profilo.</p>`;
            $eseguiBtn.disabled = false;
            $eseguiBtn.textContent = testoOriginale;
            return;
        }

        // Nessun return anticipato se il curriculum non ha "Qualifica CRI" confermate: la
        // chiamata ad /analisi va fatta comunque (anche con righe: []) perché il server deve
        // poter riconciliare — un curriculum svuotato su GAIA è di per sé un segnale valido
        // (vedi trovaQualificheRimosse() lato server).
        const { corsi: corsiGaia, senzaData } = estraiQualificheCriConfermate(esito.body);
        const patentiGaia = estraiPatentiCriConfermate(esito.body);

        $eseguiBtn.textContent = 'Analisi col catalogo LURCH…';
        let data;
        try {
            const resp = await fetch(`/api/v1/soci/${socioId}/qualifiche-gaia-analisi`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': leggiCookie('XSRF-TOKEN'),
                },
                body: JSON.stringify({ righe: corsiGaia, patenti: patentiGaia }),
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            data = await resp.json();
        } catch (err) {
            $risultato.innerHTML = `<p>Curriculum letto ma l'analisi col catalogo LURCH è fallita: ${err.message}</p>`;
            $eseguiBtn.disabled = false;
            $eseguiBtn.textContent = testoOriginale;
            return;
        }

        const patenti = data.patenti || [];
        $dialog._righeAnalisi = [
            ...data.righe,
            ...patenti,
            ...senzaData.map((nome) => creaRigaErroreDataMancante(nome)),
        ];
        $dialog._filtro = 'tutti';
        $dialog._ricerca = '';
        $dialog.querySelector('[data-ruolo="ricerca"]').value = '';
        renderFiltri($dialog);
        renderTabellaFiltrata($dialog);

        // L'importazione riguarda sempre TUTTE le righe analizzate (qualifiche + patenti),
        // indipendentemente dal filtro/ricerca visibili in questo momento.
        const riep = costruisciRiepilogo($dialog._righeAnalisi, { senzaData: senzaData.length });
        $riepilogo.textContent = riep.testo;
        $confermaBtn.style.display = riep.haAzioni ? 'inline-block' : 'none';

        $eseguiBtn.disabled = false;
        $eseguiBtn.textContent = testoOriginale;
    }

    async function eseguiImportazione($dialog, socioId) {
        const $confermaBtn = $dialog.querySelector('[data-ruolo="conferma"]');
        const $riepilogo = $dialog.querySelector('[data-ruolo="riepilogo"]');

        const testoOriginale = $confermaBtn.textContent;
        $confermaBtn.disabled = true;
        $confermaBtn.textContent = 'Importazione in corso…';

        try {
            const resp = await fetch(`/api/v1/soci/${socioId}/qualifiche-gaia-importa`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': leggiCookie('XSRF-TOKEN'),
                },
                body: JSON.stringify({ righe: $dialog._righeAnalisi }),
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const data = await resp.json();

            let messaggio = `Importazione completata: ${data.inseriti} nuove, ${data.aggiornati} aggiornate`;
            if (data.ricalcolate > 0) messaggio += `, ${data.ricalcolate} scadenze ricalcolate`;
            if (data.rimossi > 0) messaggio += `, ${data.rimossi} rimosse`;
            if (data.errori.length > 0) messaggio += `, ${data.errori.length} errori`;
            messaggio += '. Ricarico la pagina per aggiornare la tabella qualifiche…';

            $riepilogo.textContent = messaggio;
            $confermaBtn.style.display = 'none';
            // La tabella Qualifiche sotto la modale è un RelationManager Filament/Livewire:
            // non c'è un modo pulito di forzarne il refresh dallo userscript (fuori dal
            // contesto Livewire), quindi si ricarica la pagina — stesso approccio già usato in
            // eseguiRicercaIdSocio() per lo stesso motivo.
            setTimeout(() => window.location.reload(), 1500);
        } catch (err) {
            $riepilogo.textContent = 'Importazione fallita: ' + err.message;
            $confermaBtn.disabled = false;
            $confermaBtn.textContent = testoOriginale;
        }
    }

    // ------------------------------------------------------------------------
    // Import GAIA batch multi-socio per comitato locale (pagina Qualifiche soci) — riusa
    // integralmente cercaSuGaia/estraiIdProfilo/leggiCurriculum/estraiQualificheCriConfermate e
    // STATI_BADGE/FILTRI/renderFiltri/renderTabellaFiltrata definiti sopra; l'unica differenza
    // di rendering è la colonna "Socio" (mostraSocio, vedi mostraRisultatoCurriculum). Stato
    // raccolto vive su $dialog._sociRaccolti/_idGaiaTrovati, stesso principio di
    // $dialog._righeAnalisi già usato dalla modale singolo-socio.
    // ------------------------------------------------------------------------

    const BATCH_MODAL_ID = 'lurch-gaia-batch-modal';
    const BATCH_BTN_ID = 'lurch-gaia-batch-comitato-btn';
    const BATCH_INSTALL_SELECTOR = '[data-userscript-install="lurch-gaia-batch-comitato"]';
    const BATCH_GRADUATORIA_BTN_ID = 'lurch-gaia-batch-graduatoria-btn';
    const SPINNER_STYLE_ID = 'lurch-gaia-spinner-style';

    // @keyframes iniettato una sola volta (guardia sull'id) — un batch di centinaia di soci
    // richiede minuti di attesa reale tra un aggiornamento di testo e l'altro (throttle
    // pausaCasuale()): senza un indicatore che si muove davvero, il solo testo "Socio N/tot"
    // che cambia lentamente può sembrare che la pagina si sia bloccata.
    function iniettaStileSpinner() {
        if (document.getElementById(SPINNER_STYLE_ID)) return;
        const $style = document.createElement('style');
        $style.id = SPINNER_STYLE_ID;
        $style.textContent = `
            @keyframes lurch-gaia-spin { to { transform: rotate(360deg); } }
            .lurch-gaia-spinner {
                display: inline-block; width: 12px; height: 12px; margin-right: 6px;
                border: 2px solid #d1d5db; border-top-color: #a72863; border-radius: 50%;
                animation: lurch-gaia-spin 0.8s linear infinite; vertical-align: -2px;
            }
        `;
        document.head.appendChild($style);
    }

    // Aggiorna la riga di progresso con testo + spinner animato mentre attivo=true (raccolta in
    // corso); a raccolta finita/interrotta lo spinner va tolto (attivo=false), il messaggio
    // finale resta fermo.
    function aggiornaProgresso($dialog, testo, attivo) {
        const $progresso = $dialog.querySelector('[data-ruolo="progresso"]');
        const spinner = attivo ? '<span class="lurch-gaia-spinner"></span>' : '';
        $progresso.innerHTML = spinner + '<span></span>';
        $progresso.querySelector('span:last-child').textContent = testo;
    }

    // Barra a riempimento (fatti/totale in %) subito sotto la riga di testo — stesso scopo dello
    // spinner (dare un senso di avanzamento reale su un'attesa di minuti), ma quantifica quanto
    // manca invece di limitarsi a dire "sta girando". visibile=false la nasconde (reset a inizio
    // raccolta, o quando la raccolta è finita e non ha più senso mostrarla).
    function aggiornaBarraProgresso($dialog, fatti, totale, visibile) {
        const $barra = $dialog.querySelector('[data-ruolo="barra-progresso"]');
        $barra.style.display = visibile ? 'block' : 'none';
        if (visibile) {
            const percentuale = totale > 0 ? Math.round((fatti / totale) * 100) : 0;
            $dialog.querySelector('[data-ruolo="barra-progresso-fill"]').style.width = percentuale + '%';
        }
    }

    // titolo: personalizzato per contenitore (comitato/graduatoria) da chi apre la modale —
    // stessa struttura riusata da entrambi i bottoni (creaBottoneBatchComitato/Graduatoria),
    // mai in pagina contemporaneamente quindi nessun conflitto sull'id condiviso.
    function creaModaleBatchMultiSocio(titolo) {
        iniettaStileSpinner();
        const $dialog = document.createElement('dialog');
        $dialog.id = BATCH_MODAL_ID;
        $dialog.style.cssText = [
            'padding:0', 'border:none', 'border-radius:8px',
            'max-width:1100px', 'width:95vw', 'max-height:90vh',
            'position:fixed', 'top:50%', 'left:50%', 'transform:translate(-50%,-50%)', 'margin:0',
        ].join(';');
        $dialog.innerHTML = `
            <div style="display:flex;flex-direction:column;max-height:90vh;">
                <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;font-size:16px;">${titolo}</h3>
                    <button type="button" data-ruolo="chiudi" style="border:none;background:none;font-size:18px;cursor:pointer;line-height:1;">✕</button>
                </div>
                <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <button type="button" data-ruolo="esegui" style="${BTN_PRIMARY}">Avvia raccolta dati</button>
                    <button type="button" data-ruolo="conferma" style="${BTN_SUCCESS};display:none;">Avvia importazione</button>
                    <span data-ruolo="riepilogo" style="font-size:13px;color:#6b7280;"></span>
                </div>
                <div style="padding:8px 20px 0 20px;font-size:12px;color:#92400e;">⚠️ Non ricaricare la pagina né chiudere questa scheda durante la raccolta: l'operazione verrebbe interrotta e tutti i dati raccolti fino a quel momento andrebbero persi.</div>
                <div data-ruolo="progresso" style="padding:8px 20px 0 20px;font-size:12px;color:#6b7280;"></div>
                <div data-ruolo="barra-progresso" style="padding:6px 20px 8px 20px;display:none;">
                    <div style="background:#e5e7eb;border-radius:4px;height:6px;overflow:hidden;">
                        <div data-ruolo="barra-progresso-fill" style="background:#a72863;height:100%;width:0%;transition:width 0.2s;"></div>
                    </div>
                </div>
                <div data-ruolo="filtri-ricerca" style="padding:10px 20px;border-bottom:1px solid #e5e7eb;display:none;">
                    <div data-ruolo="filtri" style="margin-bottom:8px;"></div>
                    <input type="text" data-ruolo="ricerca" placeholder="Cerca per corso, cognome o nome..."
                        style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                </div>
                <div data-ruolo="risultato" style="padding:16px 20px;overflow:auto;flex:1;font-size:13px;"></div>
            </div>
        `;
        $dialog.querySelector('[data-ruolo="chiudi"]').addEventListener('click', () => $dialog.close());
        $dialog.querySelector('[data-ruolo="ricerca"]').addEventListener('input', (e) => {
            $dialog._ricerca = e.target.value.trim().toLowerCase();
            renderTabellaFiltrata($dialog);
        });
        document.body.appendChild($dialog);
        return $dialog;
    }

    async function eseguiAnalisiBatch($dialog, baseUrl, sociRaccolti, erroriDataMancante = []) {
        const $confermaBtn = $dialog.querySelector('[data-ruolo="conferma"]');
        const $riepilogo = $dialog.querySelector('[data-ruolo="riepilogo"]');

        let data;
        try {
            const resp = await fetch(`${baseUrl}/analisi`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': leggiCookie('XSRF-TOKEN'),
                },
                body: JSON.stringify({ soci: sociRaccolti }),
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            data = await resp.json();
        } catch (err) {
            $riepilogo.textContent = "Dati raccolti ma l'analisi col catalogo LURCH è fallita: " + err.message;
            return;
        }

        $dialog._righeAnalisi = [...data.righe, ...erroriDataMancante];
        $dialog._filtro = 'tutti';
        $dialog._ricerca = '';
        $dialog._mostraSocio = true;
        $dialog.querySelector('[data-ruolo="ricerca"]').value = '';
        renderFiltri($dialog);
        renderTabellaFiltrata($dialog);

        // sociRaccolti.length = soci il cui curriculum è stato letto con successo in questo
        // giro (righe: [] incluse, per permettere la riconciliazione — vedi
        // eseguiRaccoltaBatchInterna()), non solo quelli con qualifiche trovate.
        const riep = costruisciRiepilogo($dialog._righeAnalisi, {
            prefisso: `${sociRaccolti.length} soci processati`,
            senzaData: erroriDataMancante.length,
        });
        $riepilogo.textContent = riep.testo;
        $confermaBtn.style.display = riep.haAzioni ? 'inline-block' : 'none';
    }

    // Nessuna persistenza (niente localStorage/staging table): tutto il
    // lavoro di eseguiRaccoltaBatch vive in variabili JS locali, quindi un refresh o una
    // chiusura della tab a metà perde davvero tutto il progresso — un batch da centinaia di
    // soci può metterci anche oltre un'ora. Il prompt nativo del browser ("Sei sicuro di voler
    // uscire?") resta attivo solo per la durata della raccolta, tolto subito dopo (successo,
    // errore o interruzione per troppi fallimenti consecutivi).
    function avvisoPrimaDiUscire(e) {
        e.preventDefault();
        e.returnValue = '';
    }

    async function eseguiRaccoltaBatch($dialog, baseUrl) {
        window.addEventListener('beforeunload', avvisoPrimaDiUscire);
        try {
            await eseguiRaccoltaBatchInterna($dialog, baseUrl);
        } finally {
            window.removeEventListener('beforeunload', avvisoPrimaDiUscire);
        }
    }

    // Fase A: raccolta sequenziale (mai in parallelo) di ID GAIA + curriculum per ogni socio del
    // comitato, con pausaCasuale() (2-5s) tra una richiesta GM_xmlhttpRequest e la successiva —
    // applicata dopo la ricezione della risposta precedente, non sommata al suo tempo di
    // risposta. Tutto resta in memoria (nessuna scrittura) finché la Fase B (analisi) e la
    // conferma finale non arrivano.
    async function eseguiRaccoltaBatchInterna($dialog, baseUrl) {
        const $eseguiBtn = $dialog.querySelector('[data-ruolo="esegui"]');
        const $confermaBtn = $dialog.querySelector('[data-ruolo="conferma"]');
        const $riepilogo = $dialog.querySelector('[data-ruolo="riepilogo"]');
        const $risultato = $dialog.querySelector('[data-ruolo="risultato"]');

        $eseguiBtn.disabled = true;
        $confermaBtn.style.display = 'none';
        $riepilogo.textContent = '';
        $dialog.querySelector('[data-ruolo="progresso"]').innerHTML = '';
        aggiornaBarraProgresso($dialog, 0, 0, false);
        $risultato.innerHTML = '';
        $dialog.querySelector('[data-ruolo="filtri-ricerca"]').style.display = 'none';

        $eseguiBtn.textContent = 'Leggo elenco soci…';
        let soci;
        try {
            const resp = await fetch(`${baseUrl}/soci`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            soci = (await resp.json()).soci;
        } catch (err) {
            $riepilogo.textContent = "Impossibile leggere l'elenco soci da LURCH: " + err.message;
            $eseguiBtn.disabled = false;
            $eseguiBtn.textContent = 'Avvia raccolta dati';
            return;
        }

        if (soci.length === 0) {
            $riepilogo.textContent = 'Nessun socio trovato in questo comitato.';
            $eseguiBtn.disabled = false;
            $eseguiBtn.textContent = 'Avvia raccolta dati';
            return;
        }

        // Una sola chiamata di prova prima di lanciarsi sull'intero elenco: se la sessione GAIA
        // non è attiva evitiamo N richieste inutili (un batch comitato/graduatoria può coprire
        // centinaia di soci). Non sostituisce la protezione "fallimenti consecutivi" sotto, che
        // resta necessaria per il caso in cui la sessione scade A METÀ del giro, non solo prima
        // di iniziare (vedi commento su leggiCurriculum() per il bug reale che l'ha motivata).
        $eseguiBtn.textContent = 'Verifico sessione GAIA…';
        const provaSessione = await cercaSuGaia(soci[0].cf);
        if (!provaSessione.ok) {
            $riepilogo.textContent = 'GAIA non raggiungibile o sessione scaduta (status ' + provaSessione.status + '). '
                + 'Verifica di essere loggato su gaia.cri.it in questo stesso browser, poi riprova.';
            $eseguiBtn.disabled = false;
            $eseguiBtn.textContent = 'Avvia raccolta dati';
            return;
        }

        const sociRaccolti = [];
        const idGaiaTrovati = [];
        const erroriDataMancante = [];
        let erroriRaccolta = 0;

        // Fallire su UN socio (es. un'estensione scaduta o un comitato cambiato su GAIA ma non
        // ancora allineato su LURCH — dati non sempre sincronizzati al 100%) non deve abortire
        // l'intero giro, va solo saltato. Si aborta invece se si accumulano MAX_FALLIMENTI_CONSECUTIVI
        // fallimenti di fila: quello è il segnale reale di una sessione GAIA scaduta a metà giro
        // (non un problema del singolo socio), a quel punto continuare sprecherebbe solo tempo.
        const MAX_FALLIMENTI_CONSECUTIVI = 3;
        let fallimentiConsecutivi = 0;

        for (let i = 0; i < soci.length; i++) {
            const socio = soci[i];
            $eseguiBtn.textContent = 'Raccolta in corso…';
            aggiornaBarraProgresso($dialog, i, soci.length, true);

            let gaiaId = socio.gaia_persona_id;
            let idTrovatoOra = false;
            if (!gaiaId) {
                const esito = await cercaSuGaia(socio.cf);
                await pausaCasuale();
                if (esito.ok) {
                    gaiaId = estraiIdProfilo(esito.body);
                    idTrovatoOra = !!gaiaId;
                }
                if (!gaiaId) {
                    erroriRaccolta++;
                    fallimentiConsecutivi++;
                    log('ID GAIA non trovato per CF', socio.cf);
                    if (fallimentiConsecutivi >= MAX_FALLIMENTI_CONSECUTIVI) break;
                    continue;
                }
            }

            const curriculum = await leggiCurriculum(gaiaId);
            await pausaCasuale();
            if (!curriculum.ok) {
                erroriRaccolta++;
                fallimentiConsecutivi++;
                log('Curriculum non leggibile per socio', socio.socio_id, 'status', curriculum.status);
                if (fallimentiConsecutivi >= MAX_FALLIMENTI_CONSECUTIVI) break;
                continue;
            }

            fallimentiConsecutivi = 0;
            const { corsi: righe, senzaData } = estraiQualificheCriConfermate(curriculum.body);
            const patenti = estraiPatentiCriConfermate(curriculum.body);
            if (idTrovatoOra) idGaiaTrovati.push({ socio_id: socio.socio_id, gaia_persona_id: gaiaId });
            // Sempre incluso (anche righe: []), non solo se ha trovato qualifiche: il server deve
            // sapere quali soci sono stati letti con successo in questo giro per poter
            // riconciliare anche un curriculum svuotato su GAIA (vedi trovaQualificheRimosse()).
            sociRaccolti.push({ socio_id: socio.socio_id, righe, patenti });
            senzaData.forEach((nome) => erroriDataMancante.push(
                creaRigaErroreDataMancante(nome, { socio_id: socio.socio_id, cognome: socio.cognome, nome: socio.nome })
            ));

            // Scritta UNA sola volta per socio, sempre a dato completo (nome + conteggio) — mai
            // prima del fetch. Resta a video per tutta la durata del fetch del socio successivo
            // (qualche secondo, vedi pausaCasuale()), quindi un passo "indietro" rispetto alla
            // chiamata in corso in ogni momento. Prima versione (v1.10.6) scriveva anche PRIMA del
            // fetch ("Socio X — Nome", senza conteggio) e poi sovrascriveva subito dopo: le due
            // scritture sullo stesso elemento senza un await in mezzo si sovrapponevano senza mai
            // essere disegnate a video (bug reale, 2026-08-13 — si vedeva sempre e solo il nome,
            // mai il conteggio). Una sola scrittura per socio elimina il problema alla radice,
            // senza bisogno di una pausa artificiale per "dare tempo" al repaint.
            aggiornaProgresso($dialog, `Socio ${i + 1}/${soci.length} — ${socio.cognome} ${socio.nome} — ${righe.length} qualifiche, ${patenti.length} patenti`, true);
        }

        const interrotto = fallimentiConsecutivi >= MAX_FALLIMENTI_CONSECUTIVI;
        aggiornaProgresso($dialog, interrotto
            ? `Raccolta interrotta dopo ${MAX_FALLIMENTI_CONSECUTIVI} errori consecutivi (sessione GAIA probabilmente scaduta — verifica di essere loggato su gaia.cri.it, poi riprova): ${sociRaccolti.length} soci letti con successo sui soci già processati.`
            : `Raccolta completata: ${soci.length} soci processati, ${sociRaccolti.length} letti con successo, ${erroriRaccolta} errori/non trovati.`, false);
        aggiornaBarraProgresso($dialog, 0, 0, false);

        if (sociRaccolti.length === 0) {
            $riepilogo.textContent = 'Nessun socio letto con successo (sessione GAIA scaduta o CF/ID non trovati per nessuno).';
            $eseguiBtn.disabled = false;
            $eseguiBtn.textContent = 'Avvia raccolta dati';
            return;
        }

        $dialog._idGaiaTrovati = idGaiaTrovati;
        $eseguiBtn.textContent = 'Analisi col catalogo LURCH…';
        await eseguiAnalisiBatch($dialog, baseUrl, sociRaccolti, erroriDataMancante);

        $eseguiBtn.disabled = false;
        $eseguiBtn.textContent = 'Rifai raccolta dati';
    }

    // Conferma: salva prima gli eventuali ID GAIA trovati ex-novo (endpoint esistente, zero
    // modifiche), poi importa le qualifiche in blocco.
    async function eseguiImportazioneBatch($dialog, baseUrl) {
        const $confermaBtn = $dialog.querySelector('[data-ruolo="conferma"]');
        const $riepilogo = $dialog.querySelector('[data-ruolo="riepilogo"]');

        const testoOriginale = $confermaBtn.textContent;
        $confermaBtn.disabled = true;
        $confermaBtn.textContent = 'Importazione in corso…';

        if ($dialog._idGaiaTrovati && $dialog._idGaiaTrovati.length > 0) {
            try {
                const resp = await fetch('/api/v1/soci/gaia-persona-id', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': leggiCookie('XSRF-TOKEN'),
                    },
                    body: JSON.stringify({ risultati: $dialog._idGaiaTrovati }),
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
            } catch (err) {
                // Non blocca l'import qualifiche, che è indipendente: gli ID GAIA restano da
                // ritrovare in un giro futuro, ma le qualifiche già analizzate vanno comunque
                // importate.
                log('Salvataggio ID GAIA fallito (proseguo comunque con le qualifiche):', err.message);
            }
        }

        try {
            const resp = await fetch(`${baseUrl}/importa`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': leggiCookie('XSRF-TOKEN'),
                },
                body: JSON.stringify({ righe: $dialog._righeAnalisi }),
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const data = await resp.json();

            let messaggio = `Importazione completata: ${data.inseriti} nuove, ${data.aggiornati} aggiornate`;
            if (data.ricalcolate > 0) messaggio += `, ${data.ricalcolate} scadenze ricalcolate`;
            if (data.rimossi > 0) messaggio += `, ${data.rimossi} rimosse`;
            if (data.errori.length > 0) messaggio += `, ${data.errori.length} errori`;
            messaggio += '. Ricarico la pagina…';

            $riepilogo.textContent = messaggio;
            $confermaBtn.style.display = 'none';
            setTimeout(() => window.location.reload(), 1500);
        } catch (err) {
            $riepilogo.textContent = 'Importazione fallita: ' + err.message;
            $confermaBtn.disabled = false;
            $confermaBtn.textContent = testoOriginale;
        }
    }

    // Reset + apertura della modale condivisa, riusata da entrambi i bottoni (comitato e
    // graduatoria) — unica differenza tra i due è titolo e baseUrl passati dal chiamante.
    function apriModaleBatchMultiSocio(titolo, baseUrl) {
        const $dialog = document.getElementById(BATCH_MODAL_ID) || creaModaleBatchMultiSocio(titolo);
        $dialog._righeAnalisi = [];
        $dialog._filtro = 'tutti';
        $dialog._ricerca = '';
        $dialog._mostraSocio = true;
        $dialog._idGaiaTrovati = [];
        $dialog.querySelector('[data-ruolo="risultato"]').innerHTML = '';
        $dialog.querySelector('[data-ruolo="filtri-ricerca"]').style.display = 'none';
        $dialog.querySelector('[data-ruolo="ricerca"]').value = '';
        $dialog.querySelector('[data-ruolo="riepilogo"]').textContent = '';
        $dialog.querySelector('[data-ruolo="progresso"]').textContent = '';
        $dialog.querySelector('[data-ruolo="conferma"]').style.display = 'none';
        $dialog.querySelector('[data-ruolo="esegui"]').disabled = false;
        $dialog.querySelector('[data-ruolo="esegui"]').textContent = 'Avvia raccolta dati';

        $dialog.querySelector('[data-ruolo="esegui"]').onclick = () => eseguiRaccoltaBatch($dialog, baseUrl);
        $dialog.querySelector('[data-ruolo="conferma"]').onclick = () => eseguiImportazioneBatch($dialog, baseUrl);

        $dialog.showModal();
    }

    function creaBottoneBatchComitato(installBtn) {
        const baseUrl = `/api/v1/comitati-locali/${installBtn.dataset.comitatoLocaleId}/gaia-batch`;

        const $btn = document.createElement('button');
        $btn.id = BATCH_BTN_ID;
        $btn.type = 'button';
        $btn.textContent = 'Importa qualifiche GAIA (comitato)';
        // Eredita la classe Filament (colore "apre risorsa/modale") dal link che sostituisce:
        // questo bottone si limita ad aprire la modale, le operazioni vere e proprie sono dentro.
        $btn.className = installBtn.className;
        $btn.addEventListener('click', () => apriModaleBatchMultiSocio('Importa qualifiche GAIA multi-socio (comitato)', baseUrl));
        return $btn;
    }

    function creaBottoneBatchGraduatoria(installBtn) {
        const baseUrl = `/api/v1/graduatorie/${idGraduatoriaDaUrl()}/gaia-batch`;

        const $btn = document.createElement('button');
        $btn.id = BATCH_GRADUATORIA_BTN_ID;
        $btn.type = 'button';
        $btn.textContent = 'Importa qualifiche GAIA (graduatoria)';
        $btn.className = installBtn.className;
        $btn.addEventListener('click', () => apriModaleBatchMultiSocio('Importa qualifiche GAIA multi-socio (graduatoria)', baseUrl));
        return $btn;
    }

    // A differenza degli altri bottoni "Installa" (compaiono una volta e restano per sempre),
    // questo dipende da filtroLocale: ogni cambio di comitato nei filtri della pagina Qualifiche
    // fa ri-renderizzare l'header action Livewire, che ripristina il link "Installa" originale
    // (con data-comitato-locale-id aggiornato al nuovo comitato) al posto del nostro bottone.
    // L'observer qui sotto non deve MAI disconnettersi: deve reinstallare il bottone ogni volta
    // che l'"Installa" ricompare, per tutta la vita della pagina.
    function aggiungiPulsanteBatchComitato() {
        // Guardia contro sovrapposizioni: gestisciBottoneInstalla() è asincrona (fa una vera
        // fetch di rete per versionePubblicata()). Senza questo flag, ogni mutazione DOM che
        // arriva PRIMA che la fetch precedente sia risolta ripassa il controllo
        // "document.getElementById(BATCH_BTN_ID)" (ancora assente, il bottone non è stato
        // ancora sostituito) e avvia un'ALTRA gestisciBottoneInstalla() sullo stesso nodo — su
        // una pagina con mutazioni DOM frequenti (polling Livewire, notifiche) e una fetch più
        // lenta del solito, queste chiamate si accumulano senza limite invece di aspettare la
        // precedente, causando un freeze da esaurimento di fetch/promise concorrenti pendenti.
        let verificaInCorso = false;

        function provaSostituire() {
            if (document.getElementById(BATCH_BTN_ID)) return true;
            if (verificaInCorso) return false;
            const installBtn = document.querySelector(BATCH_INSTALL_SELECTOR);
            if (!installBtn) return false;

            verificaInCorso = true;
            gestisciBottoneInstalla(installBtn, creaBottoneBatchComitato)
                .then((sostituito) => {
                    if (sostituito) log('Bottone "Installa" sostituito con "Importa qualifiche GAIA (comitato)".');
                })
                .finally(() => { verificaInCorso = false; });
            return false;
        }

        provaSostituire();

        const observer = new MutationObserver(() => provaSostituire());
        observer.observe(document.body, { childList: true, subtree: true });
    }

    const CERCA_ID_SOCIO_BTN_ID = 'lurch-gaia-cerca-id-socio-btn';

    // Riusa cercaSuGaia()/estraiIdProfilo() (già usati dal bridge candidati) per un socio
    // singolo, e lo stesso endpoint di scrittura POST /api/v1/soci/gaia-persona-id — nessuna
    // logica duplicata, solo un punto d'ingresso diverso (un CF alla volta invece di una lista).
    async function eseguiRicercaIdSocio($btn, cf, socioId) {
        const testoOriginale = $btn.textContent;
        $btn.disabled = true;
        $btn.textContent = 'Cerco su GAIA…';

        const esito = await cercaSuGaia(cf);
        if (!esito.ok) {
            alert(
                'GAIA non raggiungibile o sessione scaduta (status ' + esito.status + ').\n'
                + 'Verifica di essere loggato su gaia.cri.it in questo stesso browser, poi riprova.'
            );
            $btn.disabled = false;
            $btn.textContent = testoOriginale;
            return;
        }

        const idProfilo = estraiIdProfilo(esito.body);
        if (!idProfilo) {
            alert('Nessun profilo GAIA trovato per questo codice fiscale.');
            $btn.disabled = false;
            $btn.textContent = testoOriginale;
            return;
        }

        $btn.textContent = 'Salvo su LURCH…';
        try {
            const resp = await fetch('/api/v1/soci/gaia-persona-id', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': leggiCookie('XSRF-TOKEN'),
                },
                body: JSON.stringify({ risultati: [{ socio_id: socioId, gaia_persona_id: idProfilo }] }),
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            window.location.reload();
        } catch (err) {
            alert('ID GAIA trovato (' + idProfilo + ') ma il salvataggio su LURCH è fallito: ' + err.message);
            $btn.disabled = false;
            $btn.textContent = testoOriginale;
        }
    }

    function creaBottoneCercaIdSocio(installBtn) {
        const cf = installBtn.dataset.cf;
        const socioId = installBtn.dataset.socioId;

        const $btn = document.createElement('button');
        $btn.id = CERCA_ID_SOCIO_BTN_ID;
        $btn.type = 'button';
        $btn.textContent = 'Cerca ID GAIA';
        $btn.style.cssText = BTN_PRIMARY; // esegue subito l'operazione di ricerca
        $btn.addEventListener('click', () => eseguiRicercaIdSocio($btn, cf, socioId));
        return $btn;
    }

    function creaBottoneCurriculum(installBtn) {
        const gaiaPersonaId = installBtn.dataset.gaiaPersonaId;
        const socioId = installBtn.dataset.socioId;

        const $btn = document.createElement('button');
        $btn.id = CURRICULUM_BTN_ID;
        $btn.type = 'button';
        $btn.textContent = 'Importa CV GAIA';
        // Eredita la classe Filament (colore "apre risorsa/modale") dal link che sostituisce:
        // questo bottone si limita ad apire la modale, l'operazione vera e propria è dentro
        // ("Leggi curriculum GAIA"/"Avvia importazione", colorati per la propria funzione).
        $btn.className = installBtn.className;

        // Il bottone "Importa CV" ora compare sempre insieme a "Cerca ID GAIA" (non più
        // mutuamente esclusivi, vedi aggiungiPulsantiPaginaSocio() sotto) — se il socio non ha
        // ancora un gaia_persona_id non c'è nulla da leggere: disabilitato con motivo, non
        // nascosto (stessa convenzione disabled+tooltip usata lato Filament).
        if (!gaiaPersonaId) {
            $btn.disabled = true;
            $btn.title = 'Nessun ID GAIA collegato: cerca prima l\'ID GAIA.';
            return $btn;
        }

        $btn.addEventListener('click', () => {
            const $dialog = document.getElementById(MODAL_ID) || creaModaleCurriculum();
            $dialog._righeAnalisi = [];
            $dialog._filtro = 'tutti';
            $dialog._ricerca = '';
            $dialog.querySelector('[data-ruolo="risultato"]').innerHTML = '';
            $dialog.querySelector('[data-ruolo="filtri-ricerca"]').style.display = 'none';
            $dialog.querySelector('[data-ruolo="ricerca"]').value = '';
            $dialog.querySelector('[data-ruolo="riepilogo"]').textContent = '';
            $dialog.querySelector('[data-ruolo="conferma"]').style.display = 'none';

            $dialog.querySelector('[data-ruolo="esegui"]').onclick = () => eseguiLetturaCurriculum($dialog, gaiaPersonaId, socioId);
            $dialog.querySelector('[data-ruolo="conferma"]').onclick = () => eseguiImportazione($dialog, socioId);

            $dialog.showModal();
        });
        return $btn;
    }

    // Pagina Socio: un solo bottone "Installa" lato Filament (data-userscript-install=
    // "lurch-gaia-socio-hub", vedi ViewSocio::installaUserscriptGaiaAction()) — se la versione
    // combacia, gestisciBottoneInstalla() lo sostituisce con un wrapper che contiene i DUE
    // bottoni funzionanti (Cerca ID GAIA + Importa CV GAIA, quest'ultimo disabilitato se manca
    // ancora l'ID). Stesso pattern di aggiungiPulsantiPaginaCandidati() — l'header di questa
    // pagina è renderizzato in modo sincrono (vedi il commento su installaUserscriptGaiaAction()
    // lato PHP), quindi non serve un MutationObserver: il bottone è già nel DOM al primo giro.
    function creaBottoniPaginaSocio(installBtn) {
        const $wrapper = document.createElement('span');
        $wrapper.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;';
        $wrapper.append(creaBottoneCercaIdSocio(installBtn), creaBottoneCurriculum(installBtn));
        return $wrapper;
    }

    function aggiungiPulsantiPaginaSocio() {
        if (document.getElementById(CURRICULUM_BTN_ID) && document.getElementById(CERCA_ID_SOCIO_BTN_ID)) return;

        const installBtn = document.querySelector(SOCIO_HUB_INSTALL_SELECTOR);
        if (!installBtn) return;

        gestisciBottoneInstalla(installBtn, creaBottoniPaginaSocio);
    }

    // @include generico su /admin/*: ogni funzionalità si attiva solo sulla propria pagina.
    if (idGraduatoriaDaUrl()) {
        aggiungiPulsantiPaginaCandidati();
    }
    if (paginaSocioDaUrl()) {
        aggiungiPulsantiPaginaSocio();
    }
    if (paginaQualificheSociDaUrl()) {
        aggiungiPulsanteBatchComitato();
    }
})();
