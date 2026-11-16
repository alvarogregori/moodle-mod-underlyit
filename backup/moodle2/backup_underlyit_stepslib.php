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

/**
 * Backup structure step for the Underly.it activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup structure of an Underly.it activity.
 */
class backup_underlyit_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the activity, document and highlight elements.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $activity = new backup_nested_element('underlyit', ['id'], [
            'name', 'intro', 'introformat', 'completionprogress', 'completionmatch', 'showreference',
            'timecreated', 'timemodified',
        ]);
        $documents = new backup_nested_element('documents');
        $document = new backup_nested_element('document', ['id'], [
            'userid', 'title', 'filename', 'mimetype', 'filesize', 'contenthash', 'textlength',
            'timecreated', 'timemodified',
        ]);
        $highlights = new backup_nested_element('highlights');
        $highlight = new backup_nested_element('highlight', ['id'], [
            'userid', 'selectedtext', 'selectorjson', 'pagenumber', 'color', 'headingcontext',
            'positionorder', 'reference', 'timecreated', 'timemodified',
        ]);

        $activity->add_child($documents);
        $documents->add_child($document);
        $document->add_child($highlights);
        $highlights->add_child($highlight);

        $activity->set_source_table('underlyit', ['id' => backup::VAR_ACTIVITYID]);
        $document->set_source_table('underlyit_document', ['underlyitid' => backup::VAR_PARENTID]);
        if ($userinfo) {
            $highlight->set_source_table('underlyit_highlight', ['documentid' => backup::VAR_PARENTID]);
        }

        $document->annotate_ids('user', 'userid');
        $highlight->annotate_ids('user', 'userid');

        $activity->annotate_files('mod_underlyit', 'intro', null);
        $activity->annotate_files('mod_underlyit', 'documents', null);

        return $this->prepare_activity_structure($activity);
    }
}
