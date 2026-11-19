# Progettazione: Configurazione LLM Provider per Corso e Quiz

## Panoramica

Estensione del plugin `local_llmgrader` per supportare configurazioni LLM provider a tre livelli:
1. **Globale** (sito) - Impostazioni predefinite in `admin/settings.php`
2. **Per Corso** - Override delle impostazioni globali per uno specifico corso
3. **Per Quiz** - Override delle impostazioni di corso per uno specifico quiz

## Architettura di Ereditarietà

```
Configurazione Effettiva = Quiz Settings → Course Settings → Global Settings
```

**Regole di priorità:**
- Se un'impostazione è configurata a livello quiz → usa quella
- Altrimenti, se configurata a livello corso → usa quella
- Altrimenti → usa l'impostazione globale

## 1. Modifiche al Database

### 1.1 Nuova Tabella: `local_llmgrader_course_config`

```xml
<TABLE NAME="local_llmgrader_course_config" COMMENT="LLM provider configuration per course">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="courseid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false" COMMENT="Course ID (unique)"/>
    <FIELD NAME="enabled" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="1" SEQUENCE="false" COMMENT="Enable/disable for this course"/>
    <FIELD NAME="api_endpoint" TYPE="char" LENGTH="500" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="api_key" TYPE="char" LENGTH="500" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="model" TYPE="char" LENGTH="100" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="temperature" TYPE="number" PRECISION="3" SCALE="2" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="max_tokens" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="http_timeout" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="system_prompt" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="feedback_language" TYPE="char" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="retry_enabled" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="1" SEQUENCE="false"/>
    <FIELD NAME="max_retries" TYPE="int" LENGTH="3" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="base_retry_delay" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="max_backoff_delay" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="usermodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="courseid" TYPE="foreign" FIELDS="courseid" REFTABLE="course" REFFIELDS="id"/>
    <KEY NAME="usermodified" TYPE="foreign" FIELDS="usermodified" REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="courseid" UNIQUE="true" FIELDS="courseid"/>
  </INDEXES>
</TABLE>
```

### 1.2 Nuova Tabella: `local_llmgrader_quiz_config`

```xml
<TABLE NAME="local_llmgrader_quiz_config" COMMENT="LLM provider configuration per quiz">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="quizid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false" COMMENT="Quiz ID (unique)"/>
    <FIELD NAME="courseid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false" COMMENT="Parent course ID (for validation)"/>
    <FIELD NAME="enabled" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="1" SEQUENCE="false"/>
    <FIELD NAME="api_endpoint" TYPE="char" LENGTH="500" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="api_key" TYPE="char" LENGTH="500" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="model" TYPE="char" LENGTH="100" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="temperature" TYPE="number" PRECISION="3" SCALE="2" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="max_tokens" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="http_timeout" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="system_prompt" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="feedback_language" TYPE="char" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="retry_enabled" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="1" SEQUENCE="false"/>
    <FIELD NAME="max_retries" TYPE="int" LENGTH="3" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="base_retry_delay" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="max_backoff_delay" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="usermodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="quizid" TYPE="foreign" FIELDS="quizid" REFTABLE="quiz" REFFIELDS="id"/>
    <KEY NAME="courseid" TYPE="foreign" FIELDS="courseid" REFTABLE="course" REFFIELDS="id"/>
    <KEY NAME="usermodified" TYPE="foreign" FIELDS="usermodified" REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="quizid" UNIQUE="true" FIELDS="quizid"/>
    <INDEX NAME="courseid" UNIQUE="false" FIELDS="courseid"/>
  </INDEXES>
</TABLE>
```

### 1.3 Modifica a `local_llmgrader_log`

Aggiungere campi per tracciare quale configurazione è stata usata:

```xml
<FIELD NAME="configlevel" TYPE="char" LENGTH="10" NOTNULL="false" SEQUENCE="false" COMMENT="global|course|quiz"/>
<FIELD NAME="configid" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false" COMMENT="ID della configurazione usata"/>
```

## 2. Nuove Capabilities

Aggiungere a `db/access.php`:

```php
'local/llmgrader:configurecourse' => [
    'riskbitmask' => RISK_XSS | RISK_CONFIG,
    'captype' => 'write',
    'contextlevel' => CONTEXT_COURSE,
    'archetypes' => [
        'manager' => CAP_ALLOW,
        'editingteacher' => CAP_ALLOW,
    ],
],

'local/llmgrader:configurequiz' => [
    'riskbitmask' => RISK_XSS,
    'captype' => 'write',
    'contextlevel' => CONTEXT_MODULE,
    'archetypes' => [
        'manager' => CAP_ALLOW,
        'editingteacher' => CAP_ALLOW,
    ],
],

'local/llmgrader:viewconfiguration' => [
    'captype' => 'read',
    'contextlevel' => CONTEXT_COURSE,
    'archetypes' => [
        'manager' => CAP_ALLOW,
        'editingteacher' => CAP_ALLOW,
        'teacher' => CAP_ALLOW,
    ],
],
```

## 3. Classi da Implementare

### 3.1 `classes/local/config_provider.php`

Classe centrale che gestisce l'ereditarietà delle configurazioni:

```php
namespace local_llmgrader\local;

class config_provider {
    
    /**
     * Get effective configuration for a given quiz context.
     * 
     * @param int $quizid Quiz ID
     * @param int $courseid Course ID
     * @return object Effective configuration object
     */
    public static function get_effective_config(int $quizid, int $courseid): object {
        // Try quiz-level config first.
        $quizconfig = self::get_quiz_config($quizid);
        if ($quizconfig && $quizconfig->enabled) {
            return self::merge_with_defaults($quizconfig, 'quiz');
        }
        
        // Try course-level config.
        $courseconfig = self::get_course_config($courseid);
        if ($courseconfig && $courseconfig->enabled) {
            return self::merge_with_defaults($courseconfig, 'course');
        }
        
        // Fall back to global config.
        return self::get_global_config();
    }
    
    /**
     * Merge configuration with global defaults.
     * 
     * @param object $config Local configuration
     * @param string $level Configuration level
     * @return object Merged configuration
     */
    protected static function merge_with_defaults(object $config, string $level): object {
        $global = self::get_global_config();
        $effective = clone $global;
        
        // Override with local config values (only if set).
        foreach (get_object_vars($config) as $key => $value) {
            if ($value !== null && $value !== '') {
                $effective->$key = $value;
            }
        }
        
        $effective->configlevel = $level;
        $effective->configid = $config->id;
        
        return $effective;
    }
    
    // Additional methods: get_quiz_config(), get_course_config(), get_global_config()
}
```

### 3.2 `classes/local/course_config_manager.php`

Gestisce CRUD per configurazioni corso:

```php
namespace local_llmgrader\local;

class course_config_manager {
    
    /**
     * Save course configuration.
     * 
     * @param int $courseid
     * @param object $data Form data
     * @return int Configuration ID
     */
    public static function save_course_config(int $courseid, object $data): int {
        global $DB, $USER;
        
        $existing = $DB->get_record('local_llmgrader_course_config', ['courseid' => $courseid]);
        
        if ($existing) {
            // Update existing.
            $data->id = $existing->id;
            $data->timemodified = time();
            $data->usermodified = $USER->id;
            $DB->update_record('local_llmgrader_course_config', $data);
            return $existing->id;
        } else {
            // Insert new.
            $data->courseid = $courseid;
            $data->timecreated = time();
            $data->timemodified = time();
            $data->usermodified = $USER->id;
            return $DB->insert_record('local_llmgrader_course_config', $data);
        }
    }
    
    /**
     * Delete course configuration.
     * 
     * @param int $courseid
     */
    public static function delete_course_config(int $courseid): void {
        global $DB;
        $DB->delete_records('local_llmgrader_course_config', ['courseid' => $courseid]);
    }
    
    /**
     * Check if course has custom configuration.
     * 
     * @param int $courseid
     * @return bool
     */
    public static function has_course_config(int $courseid): bool {
        global $DB;
        return $DB->record_exists('local_llmgrader_course_config', ['courseid' => $courseid]);
    }
}
```

### 3.3 `classes/local/quiz_config_manager.php`

Gestisce CRUD per configurazioni quiz (simile a course_config_manager).

## 4. Interfacce di Amministrazione

### 4.1 Pagina di Configurazione Corso

**File**: `course/llmgrader_config.php`

```php
// URL: /local/llmgrader/course_config.php?courseid=XX
require_login($courseid);
require_capability('local/llmgrader:configurecourse', context_course::instance($courseid));

// Load form with current config (merged with defaults).
// Save on POST.
// Show inheritance indicator (which settings override global).
```

**Elementi UI:**
- Checkbox "Abilita configurazione personalizzata per questo corso"
- Tutti i campi delle impostazioni globali (precompilati con valori ereditati)
- Indicatore visivo per ogni campo: "Ereditato dal globale" vs "Personalizzato"
- Pulsante "Ripristina valori globali" per resettare
- Test connessione API specifico per il corso

### 4.2 Pagina di Configurazione Quiz

**File**: `mod/quiz/llmgrader_config.php` (o integrato in quiz settings)

```php
// URL: /local/llmgrader/quiz_config.php?cmid=XX
require_login($courseid);
require_capability('local/llmgrader:configurequiz', context_module::instance($cmid));

// Similar to course config but at quiz level.
```

**Integrazione con Quiz Settings:**
- Aggiungere una sezione "LLM Grader" nel form di modifica quiz
- Link dedicato nella pagina di gestione quiz per configurazione avanzata

### 4.3 Form Classes

**File**: `classes/form/course_config_form.php`

```php
namespace local_llmgrader\form;

class course_config_form extends \moodleform {
    
    protected function definition() {
        $mform = $this->_form;
        $courseconfig = $this->_customdata['courseconfig'] ?? null;
        $globalconfig = $this->_customdata['globalconfig'];
        
        // Enable/disable checkbox.
        $mform->addElement('advcheckbox', 'enabled', 
            get_string('enabled', 'local_llmgrader'),
            get_string('enabled_desc', 'local_llmgrader'));
        
        // API Endpoint with inheritance indicator.
        $mform->addElement('text', 'api_endpoint', 
            get_string('apiendpoint', 'local_llmgrader'));
        if (empty($courseconfig->api_endpoint)) {
            $mform->setDefault('api_endpoint', $globalconfig->api_endpoint);
            $mform->addHelpButton('api_endpoint', 'apiendpoint', 'local_llmgrader');
        }
        
        // ... repeat for all fields ...
        
        // Action buttons.
        $this->add_action_buttons();
    }
}
```

## 5. Modifiche al Codice Esistente

### 5.1 `classes/task/grade_attempt.php`

Aggiornare per usare la configurazione contestuale:

```php
public function execute(): void {
    // ... existing code ...
    
    $quizid = (int)$data->quizid;
    $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
    
    // Get course ID from quiz.
    $quiz = $DB->get_record('quiz', ['id' => $quizid], 'course,id', MUST_EXIST);
    $courseid = (int)$quiz->course;
    
    // Get effective configuration for this quiz.
    $config = config_provider::get_effective_config($quizid, $courseid);
    
    if (!$config->enabled) {
        mtrace("LLM grading disabled for quiz {$quizid}. Skipping.");
        return;
    }
    
    // Pass config to essay_grader.
    $essaygrader = new essay_grader($config);
    
    // ... rest of grading logic ...
}
```

### 5.2 `classes/local/essay_grader.php`

Modificare il costruttore per accettare configurazione:

```php
class essay_grader {
    
    protected object $config;
    
    public function __construct(?object $config = null) {
        if ($config === null) {
            // Fall back to global config.
            $this->config = config_provider::get_global_config();
        } else {
            $this->config = $config;
        }
        
        $this->converter = new content_converter();
        $this->client = new llm_client($this->config);
        $this->parser = new response_parser();
    }
}
```

### 5.3 `classes/local/llm_client.php`

Modificare per usare configurazione passata invece di `get_config()`:

```php
class llm_client {
    
    protected object $config;
    
    public function __construct(object $config) {
        $this->config = $config;
        $this->endpoint = $config->api_endpoint;
        $this->apikey = $config->api_key ?? null;
        $this->model = $config->model ?? 'gpt-4o';
        // ... etc ...
    }
}
```

### 5.4 `classes/observer.php`

Verificare se il corso/quiz ha LLM abilitato:

```php
public static function handle_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
    $quizid = $event->get_data()['other']['quizid'] ?? 0;
    $attemptid = $event->objectid;
    
    // Get course ID.
    $quiz = \context_module::instance($event->get_context()->instanceid)->get_course_context(false);
    $courseid = $quiz->id;
    
    // Check if enabled at any level.
    $config = config_provider::get_effective_config($quizid, $courseid);
    if (!$config->enabled) {
        return; // Don't queue task if disabled.
    }
    
    $task = grade_attempt::instance($attemptid, $quizid, $config->configlevel, $config->configid);
    \core\task\manager::queue_adhoc_task($task);
}
```

## 6. Nuove Stringhe di Lingua

Aggiungere a `lang/it/local_llmgrader.php`:

```php
// Course/Quiz configuration.
$string['courseconfigheading'] = 'Configurazione LLM per Corso';
$string['quizconfigheading'] = 'Configurazione LLM per Quiz';
$string['configurecourse'] = 'Configura LLM Grader per questo corso';
$string['configurequiz'] = 'Configura LLM Grader per questo quiz';
$string['viewconfiguration'] = 'Visualizza configurazione LLM';
$string['configlevelglobal'] = 'Globale (sito)';
$string['configlevelcourse'] = 'Corso';
$string['configlevelquiz'] = 'Quiz';
$string['inheritedfromglobal'] = 'Ereditato dalle impostazioni globali';
$string['customoverride'] = 'Personalizzato (override)';
$string['resetto_global'] = 'Ripristina valori globali';
$string['courseconfigenabled'] = 'Abilita configurazione personalizzata';
$string['courseconfigenabled_desc'] = 'Attiva impostazioni LLM specifiche per questo corso, override delle impostazioni globali.';
$string['quizconfigenabled'] = 'Abilita configurazione personalizzata';
$string['quizconfigenabled_desc'] = 'Attiva impostazioni LLM specifiche per questo quiz.';
$string['noquizconfig'] = 'Questo quiz utilizza le impostazioni del corso o globali.';
$string['nocourseconfig'] = 'Questo corso utilizza le impostazioni globali.';
$string['configinheritance'] = 'Ereditarietà configurazione';
$string['currentconfiglevel'] = 'Livello configurazione attivo: {$a}';
$string['testconnectioncourse'] = 'Verifica connessione (Corso)';
$string['testconnectionquiz'] = 'Verifica connessione (Quiz)';

// Privacy.
$string['privacy:metadata:course_config'] = 'Configurazioni LLM per corso';
$string['privacy:metadata:quiz_config'] = 'Configurazioni LLM per quiz';
```

## 7. Privacy API Updates

Aggiornare `classes/privacy/provider.php`:

```php
public static function get_metadata(collection $collection): collection {
    // Existing table.
    $collection->add_database_table('local_llmgrader_log', [...], 'privacy:metadata:llmgrader_log');
    
    // New tables.
    $collection->add_database_table(
        'local_llmgrader_course_config',
        [
            'courseid' => 'privacy:metadata:course_config:courseid',
            'api_endpoint' => 'privacy:metadata:course_config:api_endpoint',
            'model' => 'privacy:metadata:course_config:model',
            'usermodified' => 'privacy:metadata:course_config:usermodified',
            'timemodified' => 'privacy:metadata:course_config:timemodified',
        ],
        'privacy:metadata:course_config'
    );
    
    $collection->add_database_table(
        'local_llmgrader_quiz_config',
        [
            'quizid' => 'privacy:metadata:quiz_config:quizid',
            'api_endpoint' => 'privacy:metadata:quiz_config:api_endpoint',
            'model' => 'privacy:metadata:quiz_config:model',
            'usermodified' => 'privacy:metadata:quiz_config:usermodified',
            'timemodified' => 'privacy:metadata:quiz_config:timemodified',
        ],
        'privacy:metadata:quiz_config'
    );
    
    return $collection;
}

// Add deletion methods for course/quiz configs.
```

## 8. Upgrade Script

Aggiornare `db/upgrade.php`:

```php
function xmldb_llmgrader_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();
    
    if ($oldversion < 2026111900) {
        // Create course_config table.
        $table = new xmldb_table('local_llmgrader_course_config');
        // ... define fields, keys, indexes ...
        if (!$dbman->table_exists($table)) {
            $dbman->add_table($table);
        }
        
        // Create quiz_config table.
        $table = new xmldb_table('local_llmgrader_quiz_config');
        // ... define fields, keys, indexes ...
        if (!$dbman->table_exists($table)) {
            $dbman->add_table($table);
        }
        
        // Add configlevel and configid to log table.
        $table = new xmldb_table('local_llmgrader_log');
        $field1 = new xmldb_field('configlevel', XMLDB_TYPE_CHAR, 10, null, null, null, null, 'error');
        $field2 = new xmldb_field('configid', XMLDB_TYPE_INTEGER, 10, null, null, null, null, 'configlevel');
        if (!$dbman->field_exists($table, 'configlevel')) {
            $dbman->add_field($table, $field1);
        }
        if (!$dbman->field_exists($table, 'configid')) {
            $dbman->add_field($table, $field2);
        }
        
        upgrade_plugin_savepoint(true, 2026111900, 'local', 'llmgrader');
    }
    
    return true;
}
```

## 9. Test da Implementare

### 9.1 Unit Test: `config_provider_test.php`

```php
class config_provider_test extends \advanced_testcase {
    
    public function test_quiz_config_overrides_course(): void {
        // Create course with custom config.
        // Create quiz with different custom config.
        // Verify effective config uses quiz settings.
    }
    
    public function test_course_config_overrides_global(): void {
        // Create course with custom config.
        // No quiz config.
        // Verify effective config uses course settings.
    }
    
    public function test_fallback_to_global(): void {
        // No course or quiz config.
        // Verify effective config uses global settings.
    }
    
    public function test_disabled_at_course_level(): void {
        // Course config disabled.
        // Quiz has no config.
        // Verify grading is skipped.
    }
}
```

### 9.2 Integration Test

- Creare corso con configurazione personalizzata
- Creare quiz con configurazione diversa
- Submit quiz attempt
- Verificare che il log registri il corretto `configlevel` e `configid`

## 10. Documentazione da Aggiornare

### 10.1 README.md

Aggiungere sezione:

```markdown
## Configurazione Multi-Livello

Il plugin supporta configurazioni LLM a tre livelli:

1. **Globale**: Impostazioni predefinite inAmministrazione sito → Plugins → Local plugins → LLM Grader
2. **Per Corso**: Override in `/local/llmgrader/course_config.php?courseid=X`
3. **Per Quiz**: Override in `/local/llmgrader/quiz_config.php?cmid=X`

### Ereditarietà

Le impostazioni seguono questa priorità:
- Quiz → Corso → Globale

Se un'impostazione non è configurata a livello quiz, viene ereditata dal corso.
Se non è configurata a livello corso, viene ereditata dalle impostazioni globali.

### Casi d'Uso

- **Dipartimenti diversi**: Ogni dipartimento può usare un provider LLM diverso
- **Corsi con budget separati**: API key diverse per corsi con finanziamenti differenti
- **Quiz sperimentali**: Testare modelli LLM diversi per quiz specifici
- **Conformità dati**: Corsi con requisiti di residenza dati possono usare provider regionali
```

### 10.2 docs/architecture.md

Aggiornare diagramma di flusso con configurazione contestuale.

### 10.3 docs/decisions.md

Aggiungere decisioni architetturali:

```markdown
## DEC-009: Configurazione multi-livello con ereditarietà
**Data**: YYYY-MM-DD
**Contesto**: Diversi corsi/quiz necessitano configurazioni LLM diverse
**Decisione**: Implementare tre livelli (globale, corso, quiz) con ereditarietà
**Motivazione**: Flessibilità per scenari multi-dipartimento, budget separati, conformità
```

## 11. Considerazioni di Sicurezza

### 11.1 Protezione API Key

- Le API key a livello corso/quiz devono essere criptate nel database
- Usare `core\encryption\helper` per la criptazione
- Solo utenti con `configurecourse`/`configurequiz` possono visualizzare (non modificare)

### 11.2 Capability Checks

Tutti i punti di accesso devono verificare:
```php
require_capability('local/llmgrader:configurecourse', context_course::instance($courseid));
```

### 11.3 Audit Log

Registrare tutte le modifiche alle configurazioni:
```php
\core\log\manager::get_instance()->process($event);
```

## 12. Roadmap di Implementazione

### Fase 1: Database e Core (2-3 giorni)
- [ ] Creare tabelle course_config e quiz_config
- [ ] Implementare config_provider
- [ ] Implementare course_config_manager e quiz_config_manager
- [ ] Aggiornare upgrade.php

### Fase 2: Interfacce UI (2-3 giorni)
- [ ] Creare form course_config_form
- [ ] Creare pagina course_config.php
- [ ] Creare form quiz_config_form
- [ ] Creare pagina quiz_config.php
- [ ] Aggiungere link nelle pagine corso/quiz

### Fase 3: Integrazione (1-2 giorni)
- [ ] Modificare essay_grader per accettare config
- [ ] Modificare llm_client per usare config passata
- [ ] Aggiornare observer per verificare abilitazione
- [ ] Aggiornare task per passare configlevel/log

### Fase 4: Testing e Documentazione (1-2 giorni)
- [ ] Scrivere unit test per config_provider
- [ ] Test di integrazione end-to-end
- [ ] Aggiornare README e documentazione
- [ ] Aggiornare privacy provider

**Totale stimato**: 6-10 giorni lavorativi

## 13. File da Creare/Modificare

### Nuovi File (12)
```
local/llmgrader/classes/local/config_provider.php
local/llmgrader/classes/local/course_config_manager.php
local/llmgrader/classes/local/quiz_config_manager.php
local/llmgrader/classes/form/course_config_form.php
local/llmgrader/classes/form/quiz_config_form.php
local/llmgrader/course_config.php
local/llmgrader/quiz_config.php
local/llmgrader/tests/config_provider_test.php
local/llmgrader/tests/course_config_manager_test.php
local/llmgrader/tests/quiz_config_manager_test.php
```

### File da Modificare (10)
```
local/llmgrader/db/install.xml
local/llmgrader/db/upgrade.php
local/llmgrader/db/access.php
local/llmgrader/classes/observer.php
local/llmgrader/classes/task/grade_attempt.php
local/llmgrader/classes/local/essay_grader.php
local/llmgrader/classes/local/llm_client.php
local/llmgrader/classes/privacy/provider.php
local/llmgrader/lang/it/local_llmgrader.php
local/llmgrader/README.md
local/llmgrader/docs/architecture.md
local/llmgrader/docs/decisions.md
```

## 14. Dipendenze Esterne

Nessuna dipendenza aggiuntiva richiesta. Tutte le funzionalità usano API Moodle core:
- `core\encryption\helper` per API key
- `context_course`, `context_module` per capability
- Standard Moodle forms API

## 15. Backward Compatibility

- Le configurazioni esistenti (globali) rimangono invariate
- I quiz/corsi senza configurazione personalizzata continuano a usare le impostazioni globali
- L'upgrade del database è non-distruttivo
- I log esistenti mantengono NULL per configlevel/configid (retrocompatibile)
