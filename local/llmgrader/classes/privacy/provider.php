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
 * Privacy provider for LLM Grader plugin.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider class.
 *
 * Implements the Moodle Privacy API for the llmgrader plugin.
 * The plugin stores grading logs that may contain personal data
 * (quiz attempt IDs, scores, feedback).
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_user_data_provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata about what data this plugin stores.
     *
     * @param \core_privacy\local\metadata\collection $collection The collection to add metadata to.
     * @return \core_privacy\local\metadata\collection The updated collection.
     */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $collection->add_database_table(
            'local_llmgrader_log',
            [
                'attemptid' => 'privacy:metadata:log:attemptid',
                'slot' => 'privacy:metadata:log:slot',
                'questionid' => 'privacy:metadata:log:questionid',
                'status' => 'privacy:metadata:log:status',
                'score' => 'privacy:metadata:log:score',
                'maxscore' => 'privacy:metadata:log:maxscore',
                'feedback' => 'privacy:metadata:log:feedback',
                'retrycount' => 'privacy:metadata:log:retrycount',
                'timecreated' => 'privacy:metadata:log:timecreated',
                'timemodified' => 'privacy:metadata:log:timemodified',
                'timecompleted' => 'privacy:metadata:log:timecompleted',
                'configlevel' => 'privacy:metadata:log:configlevel',
                'configid' => 'privacy:metadata:log:configid',
            ],
            'privacy:metadata:llmgrader_log'
        );

        $collection->add_database_table(
            'local_llmgrader_course_config',
            [
                'courseid' => 'privacy:metadata:course_config:courseid',
                'enabled' => 'privacy:metadata:course_config:enabled',
                'api_endpoint' => 'privacy:metadata:course_config:api_endpoint',
                'model' => 'privacy:metadata:course_config:model',
                'system_prompt' => 'privacy:metadata:course_config:system_prompt',
                'feedback_language' => 'privacy:metadata:course_config:feedback_language',
            ],
            'privacy:metadata:course_config'
        );

        $collection->add_database_table(
            'local_llmgrader_quiz_config',
            [
                'quizid' => 'privacy:metadata:quiz_config:quizid',
                'courseid' => 'privacy:metadata:quiz_config:courseid',
                'enabled' => 'privacy:metadata:quiz_config:enabled',
                'api_endpoint' => 'privacy:metadata:quiz_config:api_endpoint',
                'model' => 'privacy:metadata:quiz_config:model',
                'system_prompt' => 'privacy:metadata:quiz_config:system_prompt',
                'feedback_language' => 'privacy:metadata:quiz_config:feedback_language',
            ],
            'privacy:metadata:quiz_config'
        );

        $collection->add_external_location_link(
            'llm_provider',
            [
                'privacy:metadata:external:purpose',
            ],
            'privacy:metadata:llm_provider'
        );

        return $collection;
    }

    /**
     * Get the context IDs associated with a user's data.
     *
     * @param int $userid The user ID.
     * @return \core_privacy\local\request\contextlist The context list.
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist($userid, 'local_llmgrader');

        // Grading logs are stored at system context.
        $contextlist->add_system_context();

        return $contextlist;
    }

    /**
     * Export personal data for a list of approved contexts.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist The contexts to export from.
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $component = 'local_llmgrader';

        // Get all quiz attempts for this user.
        $attempts = $DB->get_records_select(
            'quiz_attempts',
            'userid = ? AND state = ?',
            [$userid, 'submitted'],
            'timefinish DESC'
        );

        if (!$attempts) {
            return;
        }

        $attemptids = array_keys($attempts);
        list($insql, $inparams) = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);

        // Get grading logs for these attempts.
        $logs = $DB->get_records_select(
            'local_llmgrader_log',
            "attemptid {$insql}",
            $inparams
        );

        if (!$logs) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }

            $subcontext = [$component];
            $writer = \core_privacy\local\request\writer::with_context($context);

            foreach ($logs as $log) {
                $attempt = $attempts[$log->attemptid] ?? null;
                if (!$attempt) {
                    continue;
                }

                $data = (object)[
                    'attemptid' => $log->attemptid,
                    'quizid' => $attempt->quiz,
                    'slot' => $log->slot,
                    'questionid' => $log->questionid,
                    'status' => $log->status,
                    'score' => $log->score,
                    'maxscore' => $log->maxscore,
                    'feedback' => $log->feedback,
                    'model' => $log->model,
                    'retrycount' => $log->retrycount,
                    'timecreated' => $log->timecreated,
                    'timecompleted' => $log->timecompleted,
                    'error' => $log->error,
                    'configlevel' => $log->configlevel ?? 'global',
                    'configid' => $log->configid ?? 0,
                ];

                $writer->export_related_data(
                    $subcontext,
                    "grading_log_{$log->attemptid}_{$log->slot}",
                    $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in a given context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        // Delete all grading logs (system-level data).
        $DB->delete_records('local_llmgrader_log');
    }

    /**
     * Delete specific user's data.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist The contexts containing the user's data.
     */
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $component = 'local_llmgrader';

        if ($contextlist->count() === 0) {
            return;
        }

        // Get attempts for this user.
        $attempts = $DB->get_records_select(
            'quiz_attempts',
            'userid = ?',
            [$userid],
            'id ASC',
            'id'
        );

        if (!$attempts) {
            return;
        }

        $attemptids = array_keys($attempts);
        list($insql, $inparams) = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);

        // Delete grading logs for this user's attempts.
        $DB->delete_records_select('local_llmgrader_log', "attemptid {$insql}", $inparams);
    }

    /**
     * Get the list of users who have data in a given context.
     *
     * @param \core_privacy\local\request\userlist $userlist The user list context.
     */
    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }

        // Get user IDs from quiz attempts that have grading logs.
        $sql = "SELECT DISTINCT qa.userid
                  FROM {quiz_attempts} qa
                  INNER JOIN {local_llmgrader_log} ll ON ll.attemptid = qa.id
                 WHERE qa.userid IN (SELECT id FROM {user} WHERE deleted = 0)";

        $userids = $DB->get_records_sql($sql, [], 0, 0);
        foreach ($userids as $user) {
            $userlist->add_user((int)$user->userid);
        }
    }

    /**
     * Delete data for specific users in a context.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist The approved user list.
     */
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }

        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $inparams['component'] = 'local_llmgrader';

        // Delete grading logs for these users' attempts.
        $sql = "DELETE FROM {local_llmgrader_log} ll
                  INNER JOIN {quiz_attempts} qa ON ll.attemptid = qa.id
                  WHERE qa.userid {$insql}";

        $DB->execute($sql, $inparams);
    }
}
