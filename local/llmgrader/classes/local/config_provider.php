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
 * Configuration provider - resolves multi-level LLM configuration.
 *
 * Implements inheritance: Quiz config > Course config > Global config.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Configuration provider class.
 *
 * Resolves the effective configuration by merging settings from quiz, course,
 * and global levels with proper inheritance (quiz overrides course overrides global).
 */
class config_provider {

    /** @var string Level identifier for global config */
    const LEVEL_GLOBAL = 'global';

    /** @var string Level identifier for course config */
    const LEVEL_COURSE = 'course';

    /** @var string Level identifier for quiz config */
    const LEVEL_QUIZ = 'quiz';

    /** @var array Cached effective configurations keyed by quizid:courseid */
    protected static array $cache = [];

    /**
     * Get the effective configuration for a given quiz/course context.
     *
     * Resolution order: Quiz config > Course config > Global config.
     * A null value at any level means "inherit from lower level".
     *
     * @param int $quizid Quiz ID (0 for global-only lookup)
     * @param int $courseid Course ID (0 for global-only lookup)
     * @return object Effective configuration with all settings resolved.
     */
    public static function get_effective_config(int $quizid = 0, int $courseid = 0): object {
        $cachekey = "{$quizid}:{$courseid}";

        if (isset(self::$cache[$cachekey])) {
            return self::$cache[$cachekey];
        }

        // Start with global config as base.
        $config = self::get_global_config();

        // Override with course config if available.
        if ($courseid > 0) {
            $courseconfig = self::get_course_config($courseid);
            if ($courseconfig) {
                $config = self::merge_config($config, $courseconfig, self::LEVEL_COURSE);
                $config->configlevel = self::LEVEL_COURSE;
                $config->configid = $courseid;
            } else {
                $config->configlevel = self::LEVEL_GLOBAL;
                $config->configid = 0;
            }
        } else {
            $config->configlevel = self::LEVEL_GLOBAL;
            $config->configid = 0;
        }

        // Override with quiz config if available.
        if ($quizid > 0) {
            $quizconfig = self::get_quiz_config($quizid);
            if ($quizconfig) {
                $config = self::merge_config($config, $quizconfig, self::LEVEL_QUIZ);
                $config->configlevel = self::LEVEL_QUIZ;
                $config->configid = $quizid;
            }
        }

        // Ensure all fields have concrete values (no nulls).
        $config = self::ensure_complete($config);

        // Cache the result.
        self::$cache[$cachekey] = $config;

        return $config;
    }

    /**
     * Clear the configuration cache.
     */
    public static function clear_cache(): void {
        self::$cache = [];
    }

    /**
     * Get global (site-wide) configuration.
     *
     * @return object Global configuration with defaults for missing values.
     */
    protected static function get_global_config(): object {
        return (object)[
            'enabled' => (bool)get_config('local_llmgrader', 'enabled'),
            'api_endpoint' => get_config('local_llmgrader', 'api_endpoint') ?: '',
            'api_key' => get_config('local_llmgrader', 'api_key') ?: null,
            'model' => get_config('local_llmgrader', 'model') ?: 'gpt-4o',
            'temperature' => (float)(get_config('local_llmgrader', 'temperature') ?: 0.2),
            'max_tokens' => (int)(get_config('local_llmgrader', 'max_tokens') ?: 2048),
            'http_timeout' => (int)(get_config('local_llmgrader', 'http_timeout') ?: 120),
            'system_prompt' => get_config('local_llmgrader', 'system_prompt') ?: '',
            'feedback_language' => get_config('local_llmgrader', 'feedback_language') ?: 'it',
            'retry_enabled' => (bool)(get_config('local_llmgrader', 'retry_enabled') ?? 1),
            'max_retries' => (int)(get_config('local_llmgrader', 'max_retries') ?: 8),
            'base_retry_delay' => (int)(get_config('local_llmgrader', 'base_retry_delay') ?: 5),
            'max_backoff_delay' => (int)(get_config('local_llmgrader', 'max_backoff_delay') ?: 900),
            'configlevel' => self::LEVEL_GLOBAL,
            'configid' => 0,
        ];
    }

    /**
     * Get course-level configuration.
     *
     * @param int $courseid Course ID.
     * @return object|null Course config record or null if not configured.
     */
    protected static function get_course_config(int $courseid): ?object {
        global $DB;

        try {
            $result = $DB->get_record('local_llmgrader_course_config', ['courseid' => $courseid]);
            return $result ?: null;
        } catch (\dml_exception $e) {
            // Table may not exist yet (pre-upgrade).
            return null;
        }
    }

    /**
     * Get quiz-level configuration.
     *
     * @param int $quizid Quiz ID.
     * @return object|null Quiz config record or null if not configured.
     */
    protected static function get_quiz_config(int $quizid): ?object {
        global $DB;

        try {
            $result = $DB->get_record('local_llmgrader_quiz_config', ['quizid' => $quizid]);
            return $result ?: null;
        } catch (\dml_exception $e) {
            // Table may not exist yet (pre-upgrade).
            return null;
        }
    }

    /**
     * Merge two configurations, with overrides taking precedence.
     *
     * Only non-null values from the override config replace base values.
     * The 'enabled' field is handled specially: if override has it set explicitly (0 or 1),
     * it replaces the base value regardless.
     *
     * @param object $base Base configuration.
     * @param object $override Override configuration.
     * @param string $level The level identifier for tracing.
     * @return object Merged configuration.
     */
    protected static function merge_config(object $base, object $override, string $level): object {
        $merged = clone $base;

        $mergefields = [
            'enabled',
            'api_endpoint',
            'api_key',
            'model',
            'temperature',
            'max_tokens',
            'http_timeout',
            'system_prompt',
            'feedback_language',
            'retry_enabled',
            'max_retries',
            'base_retry_delay',
            'max_backoff_delay',
        ];

        foreach ($mergefields as $field) {
            if (isset($override->$field)) {
                $value = $override->$field;

                // For numeric fields, null means "inherit", but explicit 0/1 for boolean fields counts.
                if ($value === null && $field !== 'enabled' && $field !== 'retry_enabled') {
                    continue;
                }

                // Type coercion to match expected types.
                switch ($field) {
                    case 'enabled':
                    case 'retry_enabled':
                        $merged->$field = (bool)$value;
                        break;
                    case 'temperature':
                        $merged->$field = (float)$value;
                        break;
                    case 'max_tokens':
                    case 'http_timeout':
                    case 'max_retries':
                    case 'base_retry_delay':
                    case 'max_backoff_delay':
                        $merged->$field = (int)$value;
                        break;
                    default:
                        $merged->$field = $value;
                        break;
                }
            }
        }

        return $merged;
    }

    /**
     * Ensure all configuration fields have concrete non-null values.
     *
     * @param object $config Configuration object.
     * @return object Ensured configuration.
     */
    protected static function ensure_complete(object $config): object {
        if (empty($config->api_endpoint)) {
            $config->api_endpoint = '';
        }
        if ($config->api_key === null) {
            $config->api_key = null;
        }
        if (empty($config->model)) {
            $config->model = 'gpt-4o';
        }
        if (!isset($config->temperature) || $config->temperature === null) {
            $config->temperature = 0.2;
        }
        if (!isset($config->max_tokens) || $config->max_tokens === null) {
            $config->max_tokens = 2048;
        }
        if (!isset($config->http_timeout) || $config->http_timeout === null) {
            $config->http_timeout = 120;
        }
        if (empty($config->system_prompt)) {
            $config->system_prompt = '';
        }
        if (empty($config->feedback_language)) {
            $config->feedback_language = 'it';
        }
        if (!isset($config->retry_enabled) || $config->retry_enabled === null) {
            $config->retry_enabled = true;
        }
        if (!isset($config->max_retries) || $config->max_retries === null) {
            $config->max_retries = 8;
        }
        if (!isset($config->base_retry_delay) || $config->base_retry_delay === null) {
            $config->base_retry_delay = 5;
        }
        if (!isset($config->max_backoff_delay) || $config->max_backoff_delay === null) {
            $config->max_backoff_delay = 900;
        }

        return $config;
    }

    /**
     * Check if LLM grading is enabled for a given quiz/course context.
     *
     * @param int $quizid Quiz ID (0 for global check).
     * @param int $courseid Course ID (0 for global check).
     * @return bool True if grading is enabled at any level in the inheritance chain.
     */
    public static function is_enabled(int $quizid = 0, int $courseid = 0): bool {
        $config = self::get_effective_config($quizid, $courseid);
        return (bool)$config->enabled;
    }

    /**
     * Get the configuration level that was used for a given context.
     *
     * @param int $quizid Quiz ID.
     * @param int $courseid Course ID.
     * @return string One of LEVEL_GLOBAL, LEVEL_COURSE, or LEVEL_QUIZ.
     */
    public static function get_config_level(int $quizid = 0, int $courseid = 0): string {
        $config = self::get_effective_config($quizid, $courseid);
        return $config->configlevel;
    }

    /**
     * Check if a specific setting is overridden at the quiz level.
     *
     * @param int $quizid Quiz ID.
     * @param string $setting Setting name.
     * @return bool True if the setting is explicitly set at quiz level.
     */
    public static function is_quiz_overridden(int $quizid, string $setting): bool {
        $quizconfig = self::get_quiz_config($quizid);
        if (!$quizconfig) {
            return false;
        }
        return isset($quizconfig->$setting) && $quizconfig->$setting !== null;
    }

    /**
     * Check if a specific setting is overridden at the course level.
     *
     * @param int $courseid Course ID.
     * @param string $setting Setting name.
     * @return bool True if the setting is explicitly set at course level.
     */
    public static function is_course_overridden(int $courseid, string $setting): bool {
        $courseconfig = self::get_course_config($courseid);
        if (!$courseconfig) {
            return false;
        }
        return isset($courseconfig->$setting) && $courseconfig->$setting !== null;
    }
}
