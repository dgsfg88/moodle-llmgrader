<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of GNU General Public License as published by
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
 * Quiz configuration form for LLM Grader plugin.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Quiz configuration form class.
 */
class quiz_config_form extends \moodleform {

    /** @var object Quiz object */
    protected $quiz;

    /** @var object Course object */
    protected $course;

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $this->quiz = $this->_customdata['quiz'] ?? null;
        $this->course = $this->_customdata['course'] ?? null;
        $this->add_header($mform, get_string('quizconfigheading', 'local_llmgrader'));

        // Enable section.
        $mform->addElement('header', 'enablehdr', get_string('generalheading', 'local_llmgrader'));
        $mform->setExpanded('enablehdr', true);

        $mform->addElement('advcheckbox', 'llmgrader_enabled', get_string('enabled', 'local_llmgrader'),
            get_string('enabled_desc', 'local_llmgrader'));
        $mform->setDefault('llmgrader_enabled', 0);

        // Connection settings.
        $mform->addElement('header', 'connectionhdr', get_string('connectionheading', 'local_llmgrader'));
        $mform->setExpanded('connectionhdr');

        $mform->addElement('text', 'llmgrader_api_endpoint', get_string('apiendpoint', 'local_llmgrader'),
            ['size' => 60]);
        $mform->setType('llmgrader_api_endpoint', PARAM_URL);
        $mform->addHelpButton('llmgrader_api_endpoint', 'apiendpoint', 'local_llmgrader');

        $mform->addElement('passwordunmask', 'llmgrader_api_key', get_string('apikey', 'local_llmgrader'));
        $mform->setType('llmgrader_api_key', PARAM_TEXT);
        $mform->addHelpButton('llmgrader_api_key', 'apikey', 'local_llmgrader');
        $mform->setAdvanced('llmgrader_api_key');

        $mform->addElement('text', 'llmgrader_model', get_string('model', 'local_llmgrader'), ['size' => 30]);
        $mform->setType('llmgrader_model', PARAM_TEXT);
        $mform->addHelpButton('llmgrader_model', 'model', 'local_llmgrader');

        // Generation settings.
        $mform->addElement('header', 'generationhdr', get_string('generationheading', 'local_llmgrader'));
        $mform->setExpanded('generationhdr');

        $mform->addElement('text', 'llmgrader_temperature', get_string('temperature', 'local_llmgrader'), ['size' => 10]);
        $mform->setType('llmgrader_temperature', PARAM_FLOAT);
        $mform->addHelpButton('llmgrader_temperature', 'temperature', 'local_llmgrader');
        $mform->setDefault('llmgrader_temperature', '0.2');
        $mform->setHint('llmgrader_temperature', '0.0 - 2.0');

        $mform->addElement('text', 'llmgrader_max_tokens', get_string('maxtokens', 'local_llmgrader'), ['size' => 10]);
        $mform->setType('llmgrader_max_tokens', PARAM_INT);
        $mform->addHelpButton('llmgrader_max_tokens', 'maxtokens', 'local_llmgrader');
        $mform->setDefault('llmgrader_max_tokens', '2048');

        $mform->addElement('text', 'llmgrader_http_timeout', get_string('http_timeout', 'local_llmgrader'), ['size' => 10]);
        $mform->setType('llmgrader_http_timeout', PARAM_INT);
        $mform->addHelpButton('llmgrader_http_timeout', 'http_timeout', 'local_llmgrader');
        $mform->setDefault('llmgrader_http_timeout', '120');

        // Prompt settings.
        $mform->addElement('header', 'prompthdr', get_string('promptheading', 'local_llmgrader'));
        $mform->setExpanded('prompthdr');

        $mform->addElement('editor', 'llmgrader_system_prompt_editor', get_string('systemprompt', 'local_llmgrader'),
            ['rows' => 8], [
                'maxfiles' => 0,
                'changeformat' => false,
                'context' => $this->course ? \context_course::instance($this->course->id) : \context_system::instance(),
            ]);
        $mform->setType('llmgrader_system_prompt', PARAM_RAW);
        $mform->addHelpButton('llmgrader_system_prompt_editor', 'systemprompt', 'local_llmgrader');

        $mform->addElement('text', 'llmgrader_feedback_language', get_string('feedbacklanguage', 'local_llmgrader'), ['size' => 5]);
        $mform->setType('llmgrader_feedback_language', PARAM_ALPHA);
        $mform->addHelpButton('llmgrader_feedback_language', 'feedbacklanguage', 'local_llmgrader');
        $mform->setDefault('llmgrader_feedback_language', 'it');

        // Retry settings.
        $mform->addElement('header', 'retryhdr', get_string('retryheading', 'local_llmgrader'));
        $mform->setExpanded('retryhdr');

        $mform->addElement('advcheckbox', 'llmgrader_retry_enabled', get_string('retryenabled', 'local_llmgrader'),
            get_string('retryenabled_desc', 'local_llmgrader'));
        $mform->setDefault('llmgrader_retry_enabled', 1);

        $mform->addElement('text', 'llmgrader_max_retries', get_string('maxretries', 'local_llmgrader'), ['size' => 10]);
        $mform->setType('llmgrader_max_retries', PARAM_INT);
        $mform->addHelpButton('llmgrader_max_retries', 'maxretries', 'local_llmgrader');
        $mform->setDefault('llmgrader_max_retries', '8');

        $mform->addElement('text', 'llmgrader_base_retry_delay', get_string('baseretrydelay', 'local_llmgrader'), ['size' => 10]);
        $mform->setType('llmgrader_base_retry_delay', PARAM_INT);
        $mform->addHelpButton('llmgrader_base_retry_delay', 'baseretrydelay', 'local_llmgrader');
        $mform->setDefault('llmgrader_base_retry_delay', '5');

        $mform->addElement('text', 'llmgrader_max_backoff_delay', get_string('maxbackoffdelay', 'local_llmgrader'), ['size' => 10]);
        $mform->setType('llmgrader_max_backoff_delay', PARAM_INT);
        $mform->addHelpButton('llmgrader_max_backoff_delay', 'maxbackoffdelay', 'local_llmgrader');
        $mform->setDefault('llmgrader_max_backoff_delay', '900');

        // Buttons.
        $this->add_action_buttons(true, get_string('saveconfig', 'local_llmgrader'));

        $submitbtn = $mform->createElement('submit', 'resettoinherit', get_string('resettoinherit', 'local_llmgrader'));
        $submitbtn->setAttributes(['class' => 'btn-secondary']);
        $mform->addElement('container', $submitbtn);
    }

    /**
     * Add a header section with inheritance info.
     *
     * @param \MoodleQuickForm $mform The form object.
     * @param string $title Header title.
     */
    protected function add_header(\MoodleQuickForm $mform, string $title): void {
        $mform->addElement('header', 'mainhdr', $title);
        $mform->setExpanded('mainhdr');

        if ($this->quiz && $this->course) {
            $modcontext = \context_module::instance($this->quiz->cmid ?? 0);
            $hasconfigure = has_capability('local/llmgrader:configurequiz', $modcontext);

            $quizname = format_string($this->quiz->name);
            $coursename = format_string($this->course->shortname);
            $description = get_string('quizconfigdesc', 'local_llmgrader', ["quiz" => $quizname, "course" => $coursename]);
            if (!$hasconfigure) {
                $description .= '<br>' . get_string('quizconfigreadonly', 'local_llmgrader');
            }
            $mform->addElement('static', 'quizconfiginfo', '', $description);
        }
    }

    /**
     * Load existing config into the form.
     *
     * @param object|null $config Existing configuration record.
     */
    public function set_data($config = null): void {
        if ($config) {
            $data = new \stdClass();
            $data->llmgrader_enabled = (bool)$config->enabled;
            $data->llmgrader_api_endpoint = $config->api_endpoint ?? '';
            $data->llmgrader_api_key = $config->api_key ?? '';
            $data->llmgrader_model = $config->model ?? '';
            $data->llmgrader_temperature = $config->temperature ?? '0.2';
            $data->llmgrader_max_tokens = $config->max_tokens ?? '';
            $data->llmgrader_http_timeout = $config->http_timeout ?? '';
            $data->llmgrader_system_prompt = $config->system_prompt ?? '';
            $data->llmgrader_system_prompt_format = FORMAT_HTML;
            $data->llmgrader_feedback_language = $config->feedback_language ?? 'it';
            $data->llmgrader_retry_enabled = (bool)$config->retry_enabled;
            $data->llmgrader_max_retries = $config->max_retries ?? '';
            $data->llmgrader_base_retry_delay = $config->base_retry_delay ?? '';
            $data->llmgrader_max_backoff_delay = $config->max_backoff_delay ?? '';
            parent::set_data($data);
        } else {
            parent::set_data();
        }
    }

    /**
     * Validate form data.
     *
     * @param array $data Form data.
     * @param array $files Form files.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['llmgrader_api_endpoint'])) {
            if (!filter_var($data['llmgrader_api_endpoint'], FILTER_VALIDATE_URL)) {
                $errors['llmgrader_api_endpoint'] = get_string('error_invalid_url', 'local_llmgrader');
            }
        }

        if (!empty($data['llmgrader_temperature'])) {
            $temp = floatval($data['llmgrader_temperature']);
            if ($temp < 0.0 || $temp > 2.0) {
                $errors['llmgrader_temperature'] = get_string('error_temperature_range', 'local_llmgrader');
            }
        }

        return $errors;
    }
}
