# Politica di sicurezza

## Versioni supportate

Gli aggiornamenti di sicurezza riguardano l'ultima versione rilasciata sul ramo `main`. Chi usa LURCH in produzione è invitato a restare aggiornato.

## Segnalare una vulnerabilità

**Non aprire una issue pubblica** e non descrivere il problema in discussioni aperte.

Usa la segnalazione privata di GitHub: nella pagina del repository, scheda **Security** → **Report a vulnerability**. Se non è disponibile, apri una issue che chieda soltanto un canale di contatto riservato, senza dettagli tecnici.

Nella segnalazione indica, se possibile:

- la versione di LURCH (campo `version` in `config/app.php`) e l'ambiente (PHP, database, server web);
- una descrizione del problema e dell'impatto;
- i passaggi per riprodurlo o una prova di concetto, senza usarla su installazioni altrui.

LURCH tratta dati personali di soci e partecipanti (anche dati relativi alla salute, come allergie e intolleranze): una vulnerabilità che li espone è considerata grave.

## Cosa aspettarsi

- Una conferma di ricezione appena possibile; il progetto ha un solo mantenitore, quindi i tempi dipendono dalla sua disponibilità.
- La valutazione della gravità e, se confermata, una correzione rilasciata il prima possibile.
- Il riconoscimento di chi ha segnalato, se lo desidera, una volta pubblicata la correzione.

Chiediamo di concedere un tempo ragionevole per correggere prima di divulgare pubblicamente i dettagli.

## Per chi installa LURCH

- Cambia subito la password del superadmin creato dal seeder iniziale (`SUPERADMIN_EMAIL` / `SUPERADMIN_PASSWORD`).
- In produzione usa `APP_DEBUG=false`, non impostare `OTP_FISSO` e configura un vero server SMTP per l'invio dei codici di accesso.
- Mantieni aggiornati PHP, le dipendenze e il database, ed esegui i backup (`scripts/backup.sh`).
- Il file `.env` contiene segreti: non deve essere raggiungibile dal web né finire in un repository.
