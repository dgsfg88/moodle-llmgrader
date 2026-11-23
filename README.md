# LLM Grader — Plugin Locale per Moodle 5.0.x

Un plugin locale per Moodle che valuta automaticamente esclusivamente le domande Essay utilizzando un servizio LLM con API compatibile con OpenAI Chat Completions.

## Requisiti

- **Moodle**: 5.0.x (verificato con 5.0.12)
- **PHP**: 8.2+
- **Database**: MariaDB 10.4+ / MySQL 8+ / PostgreSQL 13+
- **Cron**: attivo e funzionante (per esecuzione task ad hoc)

## Installazione

1. Copiare la directory `llmgrader/` nella cartella `local/` dell'installazione Moodle:
   ```
   local/llmgrader/
   ```

2. Accedere a Moodle come amministratore. Il plugin verrà rilevato automaticamente e verrà proposta l'installazione del database.

3. Confermare l'upgrade. Le tabelle `local_llmgrader_course_config`, `local_llmgrader_quiz_config` e `local_llmgrader_log` verranno create automaticamente.

## Configurazione

Accedere a: **Amministrazione sito → Plugins → Local plugins → LLM Grader**

### Impostazioni generali
- **Abilita LLM Grader**: attiva/disattiva la valutazione automatica
- **Endpoint API**: URL del provider LLM (es. `https://api.openai.com/v1`)
- **Chiave API**: chiave di autenticazione Bearer (opzionale)
- **Modello**: nome del modello LLM (es. `gpt-4o`, `claude-3-sonnet`)
- **Temperatura**: valore 0.0-2.0 (più basso = più deterministico)
- **Token massimi in output**: limite di generazione (default: 4096; con griglie a più criteri non scendere sotto 2048)
- **Timeout HTTP**: secondi prima dello scadere della richiesta (default: 300; per modelli locali lenti usare 600 o più)
- **Consenti endpoint locali/privati**: necessario per server LLM su `localhost` o rete privata; disattivato per default perché Moodle blocca le richieste SSRF verso indirizzi privati

### Prompt
- **Prompt di sistema**: istruzioni personalizzate per il modello (opzionale, predefinito in italiano)
- **Lingua del feedback**: lingua predefinita (es. `it`, `en`)

### Retry
- **Abilita retry automatico**: riprova le richieste temporaneamente fallite
- **Numero massimo di retry**: default 8
- **Delay base**: secondi iniziali per backoff esponenziale (default: 5)
- **Delay massimo backoff**: cap del backoff esponenziale (default: 900s)

## Come funziona

### Valutazione automatica Essay

1. Uno studente termina un quiz con domande Essay che contengono istruzioni di grading (`graderinfo`)
2. Il plugin rileva l'evento `attempt_submitted` e crea un task ad hoc in background
3. Il task valuta ogni domanda Essay eligible:
   - Recupera la risposta dello studente, il testo della domanda e le istruzioni di grading
   - Converte i contenuti HTML in Markdown per il prompt LLM (UTF-8 preservato)
   - Invia la richiesta al provider LLM configurato
   - Analizza e valida la risposta JSON strutturata
   - Calcola il voto come media ponderata dei criteri
   - Registra il punteggio e il feedback tramite le API della Question Engine di Moodle

### Valutazione per criterio

La griglia del docente può contenere più criteri con pesi diversi. Il modello non restituisce un voto complessivo: restituisce un oggetto per ogni criterio, con voto, voto massimo, peso e commento. Il plugin calcola il voto finale:

```
voto = somma(peso * voto / voto_massimo) / somma(pesi) * voto_massimo_domanda
```

Esempio di risposta attesa dal modello:

```json
{
  "criteria": [
    {
      "criterion": "Completezza della descrizione delle fasi",
      "score": 4,
      "max_score": 5,
      "weight": 30,
      "comment": "Descrive le fasi principali con errori minori."
    }
  ],
  "overall_comment": "Buona prova nel complesso."
}
```

Il feedback salvato in Moodle contiene una tabella riepilogativa per criterio, il feedback di ogni criterio e il commento complessivo, convertiti in HTML sicuro.

### Criteri di eleggibilità

Una domanda Essay viene valutata automaticamente solo se:
- Il tipo di domanda è **Essay**
- La versione della domanda contiene `graderinfo` non vuoto (istruzioni di grading del docente)
- La risposta dello studente è disponibile
- Non esiste già una valutazione automatica completata per questo attempt/slot/versione

### Protezione da prompt injection

- Ogni sezione non fidata (domanda, griglia, risposta dello studente) è racchiusa tra delimitatori con un nonce casuale per richiesta
- Il contratto JSON obbligatorio viene sempre aggiunto dopo il prompt personalizzato del docente e prevale su eventuali formati alternativi richiesti
- Le istruzioni contenute nella risposta dello studente vengono esplicitamente trattate come dati, mai come comandi
- La risposta dello studente non può chiudere anticipatamente un blocco, perché il nonce viene rimosso dal contenuto

### Conversione HTML ↔ Markdown

Il plugin implementa un sottosistema bidirezionale di conversione:

**HTML → Markdown**: parsing DOM custom che preserva headings, liste, tabelle, code blocks, link sicuri, formattazione inline. Rimuove script, style e contenuti nascosti. La codifica UTF-8 è dichiarata esplicitamente al parser, quindi accenti, simboli di valuta e virgolette tipografiche non vengono corrotti.

**Markdown → HTML**: utilizzo di Michelf MarkdownExtra (bundled con Moodle) + sanitizzazione con HTMLPurifier (`purify_html()`). L'HTML grezzo prodotto dal modello è disabilitato; sono bloccati JavaScript, event handler, iframe e URL non sicuri.

### Gestione errori e retry

- **Errori temporanei** (HTTP 429, 500-504, timeout): il plugin riprogramma automaticamente un task di retry con backoff esponenziale
- **Errori permanenti** (HTTP 400, 401, 403): il record viene segnato come `failed` e la domanda rimane disponibile per valutazione manuale
- **Risposte JSON difettose**: virgole finali, newline non escapati e risposte troncate vengono recuperati quando possibile; in caso di troncamento il feedback mostra un avviso esplicito
- **Idempotenza**: ogni operazione è identificata univocamente da attemptid + slot + questionid + questionversion

## Privacy

Il plugin implementa la Moodle Privacy API:
- Registra le valutazioni nella tabella `local_llmgrader_log`
- I dati degli studenti vengono inviati al provider LLM esterno solo quando necessario per la valutazione
- Supporta export e cancellazione dei dati personali su richiesta
- Non memorizza chiavi API o informazioni sensibili nei log

## Limitazioni note

1. **Valuta solo domande Essay**: altre tipologie di domanda (MCQ, Short Answer, etc.) vengono ignorate
2. **Richiede graderinfo**: le domande Essay senza istruzioni di grading non vengono valutate automaticamente
3. **Dipende dal cron Moodle**: i task ad hoc vengono eseguiti solo quando il cron gira
4. **Provider LLM esterno**: è necessario configurare un endpoint API funzionante
5. **Alpha stage**: il plugin è in fase alpha; potrebbero esserci bug e comportamenti non documentati

## Test

### Syntax check
```bash
php -l local/llmgrader/classes/local/*.php
php -l local/llmgrader/classes/task/*.php
php -l local/llmgrader/classes/observer.php
```

### PHPUnit
```bash
cd admin
php phpunit.php --filter=local_llmgrader
```

## Licenza

GNU General Public License v3 o successivo.
