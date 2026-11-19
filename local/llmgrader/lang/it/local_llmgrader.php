<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Italian language strings for LLM Grader plugin.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General strings.
$string['pluginname'] = 'LLM Grader';
$string['llmgrader:configure'] = 'Configurare LLM Grader';
$string['llmgrader:viewlog'] = 'Visualizzare i log di valutazione';
$string['llmgrader:manualregrade'] = 'Richiedere valutazione manuale';

// Settings.
$string['settingsheading'] = 'Impostazioni LLM Grader';
$string['generalheading'] = 'Impostazioni generali';
$string['enabled'] = 'Abilita LLM Grader';
$string['enabled_desc'] = 'Attiva la valutazione automatica delle domande Essay tramite LLM.';
$string['apiendpoint'] = 'Endpoint API';
$string['apiendpoint_desc'] = 'URL dell\'endpoint API compatibile con OpenAI Chat Completions (es. https://api.openai.com/v1/chat/completions).';
$string['apikey'] = 'Chiave API (opzionale)';
$string['apikey_desc'] = 'Chiave di autenticazione per l\'API LLM. Se lasciata vuota, la richiesta verrà inviata senza autenticazione Bearer.';
$string['model'] = 'Modello';
$string['model_desc'] = 'Nome del modello LLM da utilizzare (es. gpt-4o, claude-3-sonnet).';
$string['temperature'] = 'Temperatura';
$string['temperature_desc'] = 'Valore di temperatura per la generazione (0.0-2.0). Valori più bassi producono risultati più deterministici.';
$string['maxtokens'] = 'Token massimi in output';
$string['maxtokens_desc'] = 'Numero massimo di token da generare nella risposta.';
$string['http_timeout'] = 'Timeout HTTP (secondi)';
$string['http_timeout_desc'] = 'Timeout per le richieste HTTP al provider LLM.';

// Prompt settings.
$string['promptheading'] = 'Configurazione prompt';
$string['systemprompt'] = 'Prompt di sistema';
$string['systemprompt_desc'] = 'Istruzioni di sistema per il modello LLM. Se lasciato vuoto, verrà utilizzato un prompt predefinito in italiano.';
$string['feedbacklanguage'] = 'Lingua del feedback';
$string['feedbacklanguage_desc'] = 'Lingua predefinita per il feedback degli studenti (es. it, en).';

// Retry settings.
$string['retryheading'] = 'Impostazioni retry';
$string['retryenabled'] = 'Abilita retry automatico';
$string['retryenabled_desc'] = 'Riprova automaticamente le richieste fallite temporaneamente.';
$string['maxretries'] = 'Numero massimo di retry';
$string['maxretries_desc'] = 'Numero massimo di tentativi prima di segnalare un fallimento permanente.';
$string['baseretrydelay'] = 'Delay base retry (secondi)';
$string['baseretrydelay_desc'] = 'Delay iniziale per il backoff esponenziale.';
$string['maxbackoffdelay'] = 'Delay massimo backoff (secondi)';
$string['maxbackoffdelay_desc'] = 'Delay massimo per il backoff esponenziale (secondi).';

// Concurrency.
$string['concurrencyheading'] = 'Impostazioni concorrenza';
$string['maxconcurrent'] = 'Richieste massime concorrenti';
$string['maxconcurrent_desc'] = 'Numero massimo di richieste LLM simultanee.';

// Connection test.
$string['testconnection'] = 'Verifica connessione';
$string['testconnectionbutton'] = 'Verifica connessione API';
$string['connectiontestsuccess'] = 'Connessione API verificata con successo. Modello: {$a}';
$string['connectiontestfailed'] = 'Verifica connessione fallita: {$a}';

// Log and activity.
$string['logheading'] = 'Attività di valutazione recente';
$string['nogradesfound'] = 'Nessuna valutazione trovata.';
$string['statuscompleted'] = 'Completata';
$string['statuspending'] = 'In attesa';
$string['statusprocessing'] = 'In corso';
$string['statusretry_scheduled'] = 'Retry programmato';
$string['statusfailed'] = 'Fallita';
$string['statusskipped'] = 'Saltata';
$string['statusmanual_review'] = 'Revisione manuale';

// Error strings.
$string['noapiendpoint'] = 'Endpoint API non configurato. Contattare l\'amministratore del sito.';
$string['httppostfailed'] = 'Errore nella richiesta HTTP al provider LLM: {$a}';
$string['llmrequestfailed'] = 'Richiesta LLM fallita: {$a}';
$string['llmserviceunavailable'] = 'Servizio LLM non disponibile (errore del server).';
$string['llmratelimited'] = 'Limite di richieste raggiunto. Riprova più tardi.';
$string['invalidllmresponse'] = 'Risposta LLM non valida: {$a}';
$string['invalidjson'] = 'JSON non valido nella risposta LLM: {$a}';
$string['missing_score'] = 'La risposta LLM non contiene il campo "score".';
$string['missing_comment'] = 'La risposta LLM non contiene il campo "comment".';
$string['invalid_score'] = 'Il punteggio restituito dal modello non è un numero valido.';
$string['negative_score'] = 'Il punteggio restituito dal modello è negativo.';
$string['empty_comment'] = 'Il commento restituito dal modello è vuoto.';

// Privacy.
$string['privacy:metadata:llmgrader_log'] = 'Il plugin LLM Grader archivia un registro delle operazioni di valutazione per tracciare lo stato della valutazione automatica.';
$string['privacy:metadata:log:attemptid'] = 'ID del tentativo di quiz valutato.';
$string['privacy:metadata:log:slot'] = 'Numero dello slot della domanda.';
$string['privacy:metadata:log:questionid'] = 'ID della domanda valutata.';
$string['privacy:metadata:log:status'] = 'Stato dell\'operazione di valutazione.';
$string['privacy:metadata:log:score'] = 'Punteggio assegnato dalla valutazione automatica.';
$string['privacy:metadata:log:maxscore'] = 'Punteggio massimo della domanda.';
$string['privacy:metadata:log:feedback'] = 'Feedback generato dal modello LLM.';
$string['privacy:metadata:log:retrycount'] = 'Numero di tentativi di valutazione.';
$string['privacy:metadata:log:timecreated'] = 'Timestamp di creazione del record.';
$string['privacy:metadata:log:timemodified'] = 'Timestamp dell\'ultima modifica.';
$string['privacy:metadata:log:timecompleted'] = 'Timestamp di completamento.';
$string['privacy:metadata:llm_provider'] = 'I dati delle risposte degli studenti vengono inviati a un provider LLM esterno per la valutazione automatica.';
$string['privacy:metadata:external:purpose'] = 'Elaborazione esterna dei dati per la valutazione automatizzata delle risposte essay tramite Intelligenza Artificiale.';
