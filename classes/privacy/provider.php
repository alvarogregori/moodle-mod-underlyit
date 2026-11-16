<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_underlyit\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for the Underly.it activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the personal data stored by the plugin.
     *
     * @param collection $collection Collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('underlyit_document', [
            'userid' => 'privacy:metadata:userid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:underlyit_document');
        $collection->add_database_table('underlyit_highlight', [
            'userid' => 'privacy:metadata:userid',
            'selectedtext' => 'privacy:metadata:selectedtext',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:underlyit_highlight');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        return $collection;
    }

    /**
     * SQL selecting the IDs of the documents in a course module, for use in IN clauses.
     *
     * @return string
     */
    private static function documents_in_cm_sql(): string {
        return "SELECT d.id
                  FROM {underlyit_document} d
                  JOIN {course_modules} cm ON cm.instance = d.underlyitid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'underlyit'
                 WHERE cm.id = :cmid";
    }

    /**
     * Returns the contexts in which the user has data.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $base = "SELECT ctx.id
                   FROM {context} ctx
                   JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :level%1\$s
                   JOIN {modules} m ON m.id = cm.module AND m.name = 'underlyit'
                   JOIN {underlyit_document} d ON d.underlyitid = cm.instance";
        $sql = sprintf($base, 1) . " WHERE d.userid = :user1
                UNION " . sprintf($base, 2) . " JOIN {underlyit_highlight} h ON h.documentid = d.id WHERE h.userid = :user2";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'level1' => CONTEXT_MODULE,
            'level2' => CONTEXT_MODULE,
            'user1' => $userid,
            'user2' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Returns the users who have data in a context.
     *
     * @param userlist $userlist List to add to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $documents = self::documents_in_cm_sql();
        $params = ['cmid' => $context->instanceid];
        $userlist->add_from_sql('userid', "SELECT userid FROM {underlyit_document} WHERE id IN ($documents)", $params);
        $userlist->add_from_sql('userid', "SELECT userid FROM {underlyit_highlight} WHERE documentid IN ($documents)", $params);
    }

    /**
     * Exports the user's highlights and uploaded documents.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $documents = self::documents_in_cm_sql();
            $params = ['cmid' => $context->instanceid, 'userid' => $userid];
            $highlights = $DB->get_records_select(
                'underlyit_highlight',
                "userid = :userid AND documentid IN ($documents)",
                $params,
                'positionorder, id'
            );
            $uploads = $DB->get_records_select(
                'underlyit_document',
                "userid = :userid AND id IN ($documents)",
                $params,
                'id'
            );
            if (!$highlights && !$uploads) {
                continue;
            }
            $data = (object) [
                'highlights' => array_values(array_map(fn($highlight) => (object) [
                    'selectedtext' => $highlight->selectedtext,
                    'color' => $highlight->color,
                    'reference' => transform::yesno($highlight->reference),
                    'timecreated' => transform::datetime($highlight->timecreated),
                    'timemodified' => transform::datetime($highlight->timemodified),
                ], $highlights)),
                'documents' => array_values(array_map(fn($document) => (object) [
                    'filename' => $document->filename,
                    'timecreated' => transform::datetime($document->timecreated),
                ], $uploads)),
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'underlyit')], $data);
        }
    }

    /**
     * Deletes the personal data of every user in a context.
     *
     * @param \context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $documents = $DB->get_fieldset_sql(self::documents_in_cm_sql(), ['cmid' => $context->instanceid]);
        if (!$documents) {
            return;
        }
        $DB->delete_records_list('underlyit_highlight', 'documentid', $documents);
        [$insql, $params] = $DB->get_in_or_equal($documents, SQL_PARAMS_NAMED);
        $DB->set_field_select('underlyit_document', 'userid', 0, "id $insql", $params);
    }

    /**
     * Deletes the personal data of a user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                self::delete_users_in_cm($context->instanceid, [$userid]);
            }
        }
    }

    /**
     * Deletes the personal data of several users in a context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_module && $userlist->get_userids()) {
            self::delete_users_in_cm($context->instanceid, $userlist->get_userids());
        }
    }

    /**
     * Deletes the highlights of some users and anonymises their uploads.
     *
     * @param int $cmid Course module ID.
     * @param int[] $userids User IDs.
     * @return void
     */
    private static function delete_users_in_cm(int $cmid, array $userids): void {
        global $DB;
        $documents = $DB->get_fieldset_sql(self::documents_in_cm_sql(), ['cmid' => $cmid]);
        if (!$documents) {
            return;
        }
        [$dsql, $dparams] = $DB->get_in_or_equal($documents, SQL_PARAMS_NAMED, 'd');
        [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params = $dparams + $uparams;
        $DB->delete_records_select('underlyit_highlight', "documentid $dsql AND userid $usql", $params);
        $DB->set_field_select('underlyit_document', 'userid', 0, "id $dsql AND userid $usql", $params);
    }
}
