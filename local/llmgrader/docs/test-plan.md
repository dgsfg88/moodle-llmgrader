# Piano di Test — Moodle LLM Grader

## Test Unitari (PHPUnit)

### response_parser_test
| Test | Descrizione | Stato |
|------|-------------|-------|
| test_parse_valid_json | Parsing JSON valido con score, max_score, comment | Da eseguire |
| test_parse_json_from_markdown_block | Estrazione JSON da blocco markdown ```json``` | Da eseguire |
| test_score_clamped_to_maxmark | Punteggio > maxmark viene tagliato a maxmark | Da eseguire |
| test_negative_score_rejected | Punteggio negativo genera moodle_exception | Da eseguire |
| test_missing_score_rejected | JSON senza campo "score" genera eccezione | Da eseguire |
| test_missing_comment_rejected | JSON senza campo "comment" genera eccezione | Da eseguire |
| test_invalid_json_rejected | Testo non-JSON genera eccezione | Da eseguire |
| test_decimal_score | Punteggio decimale (7.5) accettato correttamente | Da eseguire |
| test_zero_score | Punteggio zero accettato | Da eseguire |
| test_nan_score_rejected | Valore "NaN" come score genera eccezione | Da eseguire |

### retry_manager_test
| Test | Descrizione | Stato |
|------|-------------|-------|
| test_http_429_is_retryable | HTTP 429 classificato come retryable | Da eseguire |
| test_http_503_is_retryable | HTTP 503 classificato come retryable | Da eseguire |
| test_http_401_not_retryable | HTTP 401 classificato come non-retryable | Da eseguire |
| test_http_400_not_retryable | HTTP 400 classificato come non-retryable | Da eseguire |
| test_exponential_backoff | Delay cresce esponenzialmente con retry_count | Da eseguire |
| test_parse_retry_after_integer | Header Retry-After: "30" → 30 secondi | Da eseguire |
| test_parse_retry_after_http_date | Header HTTP-date parsato correttamente | Da eseguire |
| test_delay_capped_at_max | Delay non supera max_backoff_delay (900s) | Da eseguire |

### html_to_markdown_test
| Test | Descrizione | Stato |
|------|-------------|-------|
| test_heading_conversion | h1→#, h2→## | Da eseguire |
| test_bold_italic_conversion | <strong>→**, <em>→* | Da eseguire |
| test_unordered_list_conversion | <ul><li> → - item | Da eseguire |
| test_ordered_list_conversion | <ol><li> → 1. item | Da eseguire |
| test_link_conversion | <a href="..."> → [text](url) | Da eseguire |
| test_inline_code_conversion | <code> → `text` | Da eseguire |
| test_plain_text_passthrough | Testo senza tag HTML restituito così com'è | Da eseguire |
| test_script_removal | Script tag rimosso dalla conversione | Da eseguire |
| test_code_block_conversion | <pre><code> → ```lang``` | Da eseguire |
| test_table_conversion | <table> → Markdown table | Da eseguire |
| test_html_entity_handling | Entità HTML decodificate correttamente | Da eseguire |
| test_italian_text_handling | Testo italiano preservato | Da eseguire |
| test_empty_content | Contenuto vuoto restituisce stringa vuota | Da eseguire |
| test_unsafe_url_blocked | URL javascript: bloccato nei link | Da eseguire |

### content_converter_test
| Test | Descrizione | Stato |
|------|-------------|-------|
| test_html_to_markdown | Conversione HTML→Markdown funziona | Da eseguire |
| test_plain_text_passthrough | FORMAT_PLAIN restituito così com'è | Da eseguire |
| test_markdown_to_html | **bold** e *italic* convertiti in HTML | Da eseguire |
| test_markdown_list_to_html | Liste Markdown → <ul>/<ol> | Da eseguire |
| test_empty_content | Contenuto vuoto gestito correttamente | Da eseguire |
| test_script_removal_in_m2h | Script injection bloccato in M→H | Da eseguire |
| test_markdown_heading_to_html | Heading Markdown → <h1> | Da eseguire |
| test_markdown_code_block_to_html | Code block → <pre><code> | Da eseguire |

### observer_test
| Test | Descrizione | Stato |
|------|-------------|-------|
| test_observer_queues_task_when_enabled | Task creato quando plugin è abilitato | Da eseguire |
| test_observer_does_nothing_when_disabled | Nessun task quando plugin disabilitato | Da eseguire |

## Test di Integrazione (Moodle locale)

### Setup ambiente
1. Avviare MySQL e Apache con `MoodleWindowsInstaller`
2. Verificare Moodle accessibile su http://localhost
3. Installare il plugin: copiare `local/llmgrader/` in `server/moodle/local/`
4. Eseguire l'upgrade del database: visitare `/admin/index.php` o CLI

### Test case
| # | Test | Procedura | Risultato Atteso | Stato |
|---|------|-----------|------------------|-------|
| 1 | Installazione plugin | Copia directory + accesso admin | Tabella creata, nessun errore | Da eseguire |
| 2 | Configurazione settings | Compilare campi settings | Valori salvati correttamente | Da eseguire |
| 3 | Verifica connessione | Click su "Verifica connessione API" | Messaggio successo/errore appropriato | Da eseguire |
| 4 | Quiz con Essay + graderinfo | Creare quiz, aggiungere essay con rubrica, submit | Task ad hoc creato in coda | Da eseguire |
| 5 | Quiz senza graderinfo | Essay senza istruzioni di grading | Domina ignorata (no task) | Da eseguire |
| 6 | Quiz con domanda non-Essay | MCQ, Short Answer | Ignorate correttamente | Da eseguire |
| 7 | HTML→Markdown conversion | Risposta studente con HTML complesso | Conversione preservando struttura | Da eseguire |
| 8 | Markdown→HTML feedback | Feedback LLM con liste e formattazione | HTML sicuro generato, script rimossi | Da eseguire |
| 9 | Punteggio decimale | Essay max 1.0, score 0.75 | Punteggio registrato correttamente | Da eseguire |
| 10 | Preservazione voto manuale | Insegnante modifica punteggio dopo grading automatico | Modifica insegnante preservata | Da eseguire |
| 11 | Retry su HTTP 429 | Mock provider che restituisce 429 | Task retry programmato correttamente | Da eseguire |
| 12 | Fallimento permanente su HTTP 401 | Mock provider 401 | Status "failed", no retry | Da eseguire |
| 13 | Privacy API | Richiedere export dati utente | Log di grading esportati | Da eseguire |

## Test Syntax Check
```bash
# Verifica sintassi PHP
php -l version.php
php -l settings.php
php -l lib.php
php -l classes/observer.php
php -l classes/task/*.php
php -l classes/local/*.php
php -l classes/privacy/*.php
php -l lang/it/local_llmgrader.php
php -l tests/*.php
```

## Esecuzione PHPUnit
```bash
cd C:\MoodleWindowsInstaller-latest-500\server\moodle
php admin\/phpunit.php --filter=local_llmgrader
```
