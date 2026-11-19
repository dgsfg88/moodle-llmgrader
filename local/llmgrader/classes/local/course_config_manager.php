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
 * Course configuration manager for LLM Grader plugin.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\local;

// MOODLE_INTERNAL check removed - class is autoloaded

/**
 * Course configuration manager class.
 *
 * Handles CRUD operations for course-level LLM Grader configuration.
 */
class course_config_manager {

    /**
     * Get or create the configuration record for a course.
     *
     * @param int $courseid Course ID.
     * @return object Configuration record (always exists after this call).
     */
    public static function get_or_create(int $courseid): object {
        global $DB;

        $config = self::get($courseid);
        if ($config) {
            return $config;
        }

        $now = time();
        $record = (object)[
            'courseid' => $courseid,
            'enabled' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $record->id = $DB->insert_record('local_llmgrader_course_config', $record);
        config_provider::clear_cache();

        return $record;
    }

    /**
     * Get the configuration record for a course.
     *
     * @param int $courseid Course ID.
     * @return object|null Configuration record or null if not configured.
     */
    public static function get(int $courseid): ?object {
        global $DB;

        try {
            return $DB->get_record('local_llmgrader_course_config', ['courseid' => $courseid]);
        } catch (\dml_exception $e) {
            return null;
        }
    }

    /**
     * Update the configuration for a course.
     *
     * @param int $courseid Course ID.
     * @param object $data Configuration data to save.
     * @return object Updated configuration record.
     */
    public static function update(int $courseid, object $data): object {
        global $DB;

        $config = self::get($courseid);

        if (!$config) {
            $data->courseid = $courseid;
            $data->timecreated = time();
            $data->timemodified = time();
            $config = (object)[
                'id' => $DB->insert_record('local_llmgrader_course_config', $data),
            ];
        } else {
            $data->id = $config->id;
            $data->courseid = $courseid;
            $data->timemodified = time();
            $DB->update_record('local_llmgrader_course_config', $data);
            $config = self::get($courseid);
        }

        config_provider::clear_cache();

        return $config;
    }

    /**
     * Delete the configuration for a course.
     *
     * @param int $courseid Course ID.
     */
    public static function delete(int $courseid): void {
        global $DB;

        $DB->delete_records('local_llmgrader_course_config', ['courseid' => $courseid]);
        config_provider::clear_cache();
    }

    /**
     * Check if a course has non-default configuration.
     *
     * @param int $courseid Course ID.
     * @return bool True if the course has explicit overrides.
     */
    public static function has_config(int $courseid): bool {
        $config = self::get($courseid);
        return $config !== null;
    }

    /**
     * Build a data object from form submission.
     *
     * @param \stdClass $formdata Form submitted data.
     * @return object Sanitized configuration data.
     */
    public static function from_form_data(\stdClass $formdata): object {
        $data = new \stdClass();

        $data->enabled = !empty($formdata->llmgrader_enabled) ? 1 : 0;
        $data->api_endpoint = !empty($formdata->llmgrader_api_endpoint) ? trim($formdata->llmgrader_api_endpoint) : null;
        $data->api_key = !empty($formdata->llmgrader_api_key) ? trim($formdata->llmgrader_api_key) : null;
        $data->model = !empty($formdata->llmgrader_model) ? trim($formdata->llmgrader_model) : null;
        $data->temperature = !empty($formdata->llmgrader_temperature) ? floatval($formdata->llmgrader_temperature) : null;
        $data->max_tokens = !empty($formdata->llmgrader_max_tokens) ? intval($formdata->llmgrader_max_tokens) : null;
        $data->http_timeout = !empty($formdata->llmgrader_http_timeout) ? intval($formdata->llmgrader_http_timeout) : null;
        $data->system_prompt = !empty($formdata->llmgrader_system_prompt) ? trim($formdata->llmgrader_system_prompt) : null;
        $data->feedback_language = !empty($formdata->llmgrader_feedback_language) ? trim($formdata->llmgrader_feedback_language) : null;
        $data->retry_enabled = !empty($formdata->llmgrader_retry_enabled) ? 1 : 0;
        $data->max_retries = !empty($formdata->llmgrader_max_retries) ? intval($formdata->llmgrader_max_retries) : null;
        $data->base_retry_delay = !empty($formdata->llmgrader_base_retry_delay) ? intval($formdata->llmgrader_base_retry_delay) : null;
        $data->max_backoff_delay = !empty($formdata->llmgrader_max_backoff_delay) ? intval($formdata->llmgrader_max_backoff_delay) : null;

        // Null out empty strings to avoid storing blank values.
        foreach (['api_endpoint', 'api_key', 'model', 'system_prompt', 'feedback_language'] as $field) {
            if ($data->$field === '') {
                $data->$field = null;
            }
        }

        // Null out zero values for numeric fields that use 0 as meaningful default.
        foreach (['max_tokens', 'http_timeout', 'max_retries', 'base_retry_delay', 'max_backoff_delay'] as $field) {
            if ($data->$field === 0) {
                $data->$field = null;
            }
        }

        return $data;
    }
}
