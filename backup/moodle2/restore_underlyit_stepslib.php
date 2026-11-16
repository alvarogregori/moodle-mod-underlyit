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
 * Restore structure step for the Underly.it activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores the structure of an Underly.it activity.
 */
class restore_underlyit_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the restore paths.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('underlyit', '/activity/underlyit'),
            new restore_path_element('underlyit_document', '/activity/underlyit/documents/document'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element(
                'underlyit_highlight',
                '/activity/underlyit/documents/document/highlights/highlight'
            );
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the activity record.
     *
     * @param array $data Backed-up data.
     * @return void
     */
    protected function process_underlyit($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $newid = $DB->insert_record('underlyit', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restores a document record.
     *
     * @param array $data Backed-up data.
     * @return void
     */
    protected function process_underlyit_document($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->underlyitid = $this->get_new_parentid('underlyit');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $newid = $DB->insert_record('underlyit_document', $data);
        $this->set_mapping('underlyit_document', $oldid, $newid);
    }

    /**
     * Restores a highlight.
     *
     * @param array $data Backed-up data.
     * @return void
     */
    protected function process_underlyit_highlight($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->documentid = $this->get_new_parentid('underlyit_document');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $newid = $DB->insert_record('underlyit_highlight', $data);
        $this->set_mapping('underlyit_highlight', $oldid, $newid);
    }


    /**
     * Restores the intro and document files.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_underlyit', 'intro', null);
        $this->add_related_files('mod_underlyit', 'documents', null);
    }
}
