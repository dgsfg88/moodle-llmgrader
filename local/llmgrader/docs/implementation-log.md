# Documentazione Implementazione — Moodle LLM Grader

## Riepilogo

Plugin locale per Moodle 5.0.x (`local_llmgrader`) che valuta automaticamente esclusivamente le domande Essay nei quiz utilizzando un'API LLM compatibile con OpenAI Chat Completions.

## Ambiente di sviluppo

| Componente | Versione | Percorso |
|------------|----------|----------|
| Moodle | 5.0.12 (Build: 20261112) | `C:\MoodleWindowsInstaller-latest-500\server\moodle` |
| PHP | 8.2.4 | `C:\MoodleWindowsInstaller-latest-500\server\php\php.exe` |
| MariaDB | 10.4.28 | `C:\MoodleWindowsInstaller-latest-500\server\mysql` |
| Apache | 2.4.56 | `C:\MoodleWindowsInstaller-latest-500\server\apache` |

## API Moodle verificate

### Evento di submission del quiz
- **Classe**: `\mod_quiz\event\attempt_submitted`
- **File**: `mod/quiz/classes/event/attempt_submitted.php`
- **Livello**: `objectid` = attempt ID, `relateduserid` = student ID, `$other['quizid']` = quiz ID
- **Snapshot disponibili**: `quiz_attempts`, `quiz`
- **Fuoco**: Quando `quiz_attempt::process_submit()` transiziona lo stato a `SUBMITTED`

### Observer registration
- **File**: `db/events.php`
- **Parametro chiave**: `'internal' => false` per esecuzione post-transazione DB
- **Callback**: riceve l'oggetto evento con accessi agli snapshot tramite `get_record_snapshot()`

### Question Engine — Essay grading
- **Classe comportamento**: `qbehaviour_manualgraded` (question/behaviour/manualgraded/behaviour.php)
- **Essay question**: `qtype_essay_question extends question_with_responses`
  - Proprietà: `graderinfo`, `graderinfoformat`, `maxmark`, `questiontext`
  - File: `question/type/essay/question.php`
- **Risposta studente**: `$qa->get_last_qt_data()` restituisce `['answer' => 'text', 'answerformat' => FORMAT_HTML]`
- **manual_grade() signature** (question_attempt):
  ```php
  public function manual_grade($comment, $mark, $commentformat = null, $timestamp = null, $userid = null)
  ```
- **Convenience wrapper** (question_usage_by_activity):
  ```php
  public function manual_grade($slot, $comment, $mark, $commentformat = null)
  ```
- **Salvataggio**: `question_engine::save_questions_usage_by_activity($quba)`

### Ad hoc Tasks
- **Classe base**: `core\task\adhoc_task` (lib/classes/task/adhoc_task.php)
- **Custom data**: JSON-encoded via `set_custom_data()` / `get_custom_data()`
- **Code execution**: `execute(): void` — lanciare eccezioni per fallimenti
- **Queue**: `\core\task\manager::queue_adhoc_task($task)` o `reschedule_or_queue_adhoc_task()`
- **Duplicate check**: secondo parametro `true` in `queue_adhoc_task()`, o `get_queued_adhoc_task_record()`

### Privacy API
- **Interfacce richieste**: `metadata\provider`, `core_user_data_provider`, `core_userlist_provider`
- **Metodi implementati**: 7 totali (get_metadata, get_contexts_for_userid, export_user_data, delete_data_for_user, delete_data_for_all_users_in_context, get_users_in_context, delete_data_for_users)
- **Tabelle con dati personali**: `local_llmgrader_log`
- **Dati esterni**: LLM provider

## Architettura implementata

### Flusso di valutazione
```
Quiz Submission Event (attempt_submitted)
    → Observer (internal=false, post-transazione)
        → Verifica plugin enabled
            → Crea ad hoc task grade_attempt
                → Carica question usage
                    → Itera slots
                        → Identifica Essay con graderinfo
                            → Converte HTML→Markdown
                                → Costruisce prompt LLM
                                    → Richiesta HTTP al provider
                                        → Parse e validazione JSON
                                            → Converti Markdown→HTML (purify)
                                                → Applica grade via manual_grade()
                                                    → Salva QUBA
                                                        → Aggiorna log
```

### Gestione retry
- **Classificazione errori**: temporanei (429, 500-504, timeout) vs permanenti (400, 401, 403)
- **Backoff esponenziale**: `min(max_delay, base_delay * 2^retry_count) + jitter`
- **Retry-After header**: supporto intero e HTTP-date
- **Max retry configurabile** (default: 8)

### Conversione contenuti
- **HTML→Markdown**: parsing DOM custom (`html_to_markdown.php`)
  - Supporta: headings, liste, tabelle, code blocks, link sicuri, bold/italic, entity
  - Rimuove: script, style, commenti, hidden elements
  - Blocca: URL javascript:, data:, etc.
- **Markdown→HTML**: `\Michelf\MarkdownExtra::defaultTransform()` + `purify_html()`

## Database

### Tabella: local_llmgrader_log

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | int(10) PK | Chiave primaria auto-incrementale |
| attemptid | int(10) | Quiz attempt ID |
| slot | int(5) | Numero slot domanda |
| questionid | int(10) | Question ID |
| questionversion | int(10) | Versione domanda utilizzata |
| status | char(30) | Stato: pending, processing, completed, retry_scheduled, failed, skipped, manual_review |
| model | char(100) | Modello LLM utilizzato |
| score | number(5,2) | Punteggio assegnato |
| maxscore | number(5,2) | Punteggio massimo |
| feedback | text | Feedback HTML (sanitizzato) |
| retrycount | int(3) | Numero di tentativi |
| nextretry | int(10) | Prossimo retry timestamp |
| lasthttpcode | int(5) | Ultimo codice HTTP ricevuto |
| lastretryreason | text | Motivo ultimo retry |
| timecreated | int(10) | Timestamp creazione |
| timemodified | int(10) | Timestamp modifica |
| timecompleted | int(10) | Timestamp completamento |
| error | text | Messaggio errore (se fallita) |

**Indici**: attemptid, status, nextretry, unique_op (attemptid+slot+questionid+questionversion)

## Struttura file

```
local/llmgrader/
├── version.php              # Definizione plugin
├── settings.php             # Pagina impostazioni admin
├── lib.php                  # Funzioni condivise
├── test.php                 # Pagina test connessione
├── db/
│   ├── access.php           # Capabilities
│   ├── events.php           # Observer registration
│   ├── tasks.php            # Ad hoc task definitions
│   ├── install.xml          # Schema database
│   └── upgrade.php          # Upgrade script
├── classes/
│   ├── observer.php         # Event observer
│   ├── task/
│   │   ├── grade_attempt.php    # Task valutazione attempt
│   │   └── retry_grading.php    # Task retry valutazione
│   ├── local/
│   │   ├── essay_grader.php       # Orchestratore grading
│   │   ├── llm_client.php         # Client HTTP LLM
│   │   ├── response_parser.php    # Parse e validazione JSON
│   │   ├── content_converter.php  # Conversione bidirezionale
│   │   ├── html_to_markdown.php   # HTML→Markdown (DOM)
│   │   ├── retry_manager.php      # Gestione retry/backoff
│   │   └── prompt_builder.php     # Costruzione prompt LLM
│   └── privacy/
│       └── provider.php         # Privacy API
├── lang/it/local_llmgrader.php  # Stringhe italiane
├── tests/
│   ├── response_parser_test.php
│   ├── retry_manager_test.php
│   ├── html_to_markdown_test.php
│   ├── content_converter_test.php
│   └── observer_test.php
└── docs/
    ├── architecture.md
    ├── implementation-log.md
    ├── test-plan.md
    └── decisions.md
```

## Dipendenze esterne

Nessuna dipendenza esterna aggiuntiva. Il plugin utilizza esclusivamente:
- API Moodle 5.0.x (Question Engine, HTTP Client, Task API, Privacy API)
- Michelf MarkdownExtra (bundled con Moodle in `lib/markdown/Michelf/`)
- HTMLPurifier (bundled con Moodle in `lib/htmlpurifier/`)

## Note implementative

1. **Event observer**: utilizzato `'internal' => false` per garantire che l'observer venga eseguito DOPO il commit della transazione DB del quiz submission. Questo evita race condition.

2. **Versione domanda**: il plugin registra la `questionversion` nel log per garantire che ogni valutazione utilizzi la versione esatta della domanda associata al tentativo, non l'ultima versione in banca domande.

3. **Sicurezza feedback**: il feedback LLM (Markdown) viene convertito in HTML tramite Michelf e poi sanitizzato con `purify_html()`. Solo tag semantici sicuri sono permessi.

4. **Prompt injection**: i contenuti dello studente sono trattati come dati non attendibili. Il prompt separa chiaramente le istruzioni del docente dal contenuto dello studente.

5. **Grades preservation**: il plugin verifica che non esista già una valutazione manuale prima di applicare un punteggio automatico.
