# Decisions — Moodle LLM Grader

## DEC-001: Utilizzo di observer con internal=false
**Data**: 2026-11-18  
**Contesto**: L'observer deve essere eseguito dopo il commit della transazione DB del quiz submission.  
**Decisione**: Impostare `'internal' => false` nella registrazione dell'observer in `db/events.php`.  
**Motivazione**: Moodle esegue gli observer interni PRIMA del commit DB (in transazione), mentre quelli esterni DOPO. Per garantire che il quiz attempt sia già salvato quando il task ad hoc inizia l'esecuzione, serve un observer esterno.

## DEC-002: Conversione HTML→Markdown con DOM parsing custom
**Data**: 2026-11-18  
**Contesto**: Moodle non include alcuna libreria HTML-to-Markdown in produzione. `league/html-to-markdown` è disponibile solo come dev dependency di Symfony.  
**Decisione**: Implementare un convertitore HTML-to-Markdown custom basato su DOMDocument parsing.  
**Motivazione**: Evitare dipendenze third-party non disponibili in Moodle. Il DOM parser custom offre controllo completo sulla conversione, inclusa la rimozione sicura di script/stili e il blocco di URL non sicuri.

## DEC-003: Utilizzo di Michelf MarkdownExtra per Markdown→HTML
**Data**: 2026-11-18  
**Contesto**: Moodle include già `Michelf\MarkdownExtra` in `lib/markdown/Michelf/`.  
**Decisione**: Utilizzare `\Michelf\MarkdownExtra::defaultTransform()` per la conversione Markdown→HTML.  
**Motivazione**: Libreria già disponibile, mantenuta da Moodle, compatibile con Moodle 5.0.x.

## DEC-004: Sanitizzazione HTML con purify_html()
**Data**: 2026-11-18  
**Contesto**: Il feedback LLM deve essere convertito in HTML sicuro prima dello storage.  
**Decisione**: Utilizzare la funzione Moodle `purify_html()` sull'output di Michelf.  
**Motivazione**: HTMLPurifier è già bundled con Moodle e fornisce sanitizzazione robusta contro XSS, tag pericolosi, URL non sicuri, etc.

## DEC-005: Valutazione tramite manual_grade() della Question Engine
**Data**: 2026-11-18  
**Contesto**: Due firme disponibili per `manual_grade()` — sulla `question_usage_by_activity` e sulla `question_attempt`.  
**Decisione**: Utilizzare la convenience method su `question_usage_by_activity` (`$quba->manual_grade($slot, $comment, $mark, $format)`).  
**Motivazione**: La convenience method è più semplice e integra automaticamente la notifica `$this->observer->notify_attempt_modified($qa)`.

## DEC-006: Tabella di log con vincolo di unicità composta
**Data**: 2026-11-18  
**Contesto**: Necessità di prevenire duplicate grading e tracciare lo stato delle valutazioni.  
**Decisione**: Creare `local_llmgrader_log` con indice unico su `(attemptid, slot, questionid, questionversion)`.  
**Motivazione**: La combinazione di questi quattro campi identifica univocamente ogni operazione di valutazione, permettendo controlli di idempotenza a livello database.

## DEC-007: Retry manager basato su classificazione errori HTTP
**Data**: 2026-11-18  
**Contesto**: Distinguere tra errori temporanei (retryable) e permanenti (non retryable).  
**Decisione**: Classificare in base al codice HTTP: 429, 5xx, timeout → retry; 400, 401, 403 → no retry.  
**Motivazione**: Segue le best practice per API REST e le specifiche del task (PROMPT_IMPLEMENTATION.md §7).

## DEC-008: Prompt in italiano come default
**Data**: 2026-11-18  
**Contesto**: Il progetto richiede l'uso dell'italiano per il feedback agli studenti.  
**Decisione**: Il system prompt predefinito è in italiano. La lingua del feedback è configurabile (`feedback_language`).  
**Motivazione**: Allineamento con la policy linguistica del progetto (AGENTS.md §Language policy).
