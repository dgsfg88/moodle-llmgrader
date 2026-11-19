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

3. Confermare l'upgrade. La tabella `local_llmgrader_log` verrà creata automaticamente.

## Configurazione

Accedere a: **Amministrazione sito → Plugins → Local plugins → LLM Grader**

### Impostazioni generali
- **Abilita LLM Grader**: attiva/disattiva la valutazione automatica
- **Endpoint API**: URL del provider LLM (es. `https://api.openai.com/v1/chat/completions`)
- **Chiave API**: chiave di autenticazione Bearer (opzionale)
- **Modello**: nome del modello LLM (es. `gpt-4o`, `claude-3-sonnet`)
- **Temperatura**: valore 0.0-2.0 (più basso = più deterministico)
- **Token massimi in output**: limite di generazione (default: 2048)
- **Timeout HTTP**: secondi prima dello scadere della richiesta

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
   - Converte i contenuti HTML in Markdown per il prompt LLM
   - Invia la richiesta al provider LLM configurato
   - Analizza e valida la risposta JSON strutturata
   - Registra il punteggio e il feedback tramite le API della Question Engine di Moodle

### Criteri di eleggibilità

Una domanda Essay viene valutata automaticamente solo se:
- Il tipo di domanda è **Essay**
- La versione della domanda contiene `graderinfo` non vuoto (istruzioni di grading del docente)
- La risposta dello studente è disponibile
- Non esiste già una valutazione automatica completata per questo attempt/slot/versione

### Conversione HTML ↔ Markdown

Il plugin implementa un sottosistema bidirezionale di conversione:

**HTML → Markdown**: parsing DOM custom che preserva headings, liste, tabelle, code blocks, link sicuri, formattazione inline. Rimuove script, style e contenuti nascosti.

**Markdown → HTML**: utilizzo di Michelf MarkdownExtra (bundled con Moodle) + sanitizzazione con HTMLPurifier (`purify_html()`). Blocca JavaScript, event handler, iframe e URL non sicuri.

### Gestione errori e retry

- **Errori temporanei** (HTTP 429, 500-504, timeout): il plugin riprogramma automaticamente un task di retry con backoff esponenziale
- **Errori permanenti** (HTTP 400, 401, 403): il record viene segnato come `failed` e la domanda rimane disponibile per valutazione manuale
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
