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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * StudyMark activity plugin.
 *
 * @package    mod_studymark
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
class restore_studymark_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $paths=[new restore_path_element('studymark','/activity/studymark'),new restore_path_element('studymark_document','/activity/studymark/documents/document')];
        if ($this->get_setting_value('userinfo')) { $paths[]=new restore_path_element('studymark_highlight','/activity/studymark/documents/document/highlights/highlight'); $paths[]=new restore_path_element('studymark_comment','/activity/studymark/documents/document/comments/comment'); }
        return $this->prepare_activity_structure($paths);
    }
    protected function process_studymark($data) { global $DB; $data=(object)$data; $data->course=$this->get_courseid(); $newid=$DB->insert_record('studymark',$data); $this->apply_activity_instance($newid); }
    protected function process_studymark_document($data) { global $DB; $data=(object)$data; $oldid=$data->id; $data->studymarkid=$this->get_new_parentid('studymark'); $data->userid=$this->get_mappingid('user',$data->userid,0); $newid=$DB->insert_record('studymark_document',$data); $this->set_mapping('studymark_document',$oldid,$newid); }
    protected function process_studymark_highlight($data) { global $DB; $data=(object)$data; $oldid=$data->id; $data->documentid=$this->get_new_parentid('studymark_document'); $data->userid=$this->get_mappingid('user',$data->userid,0); $data->groupid=$this->get_mappingid('group',$data->groupid,0); $newid=$DB->insert_record('studymark_highlight',$data); $this->set_mapping('studymark_highlight',$oldid,$newid); }
    protected function process_studymark_comment($data) { global $DB; $data=(object)$data; $data->documentid=$this->get_new_parentid('studymark_document'); $data->highlightid=$data->highlightid ? $this->get_mappingid('studymark_highlight',$data->highlightid,null) : null; $data->userid=$this->get_mappingid('user',$data->userid,0); $data->groupid=$this->get_mappingid('group',$data->groupid,0); $DB->insert_record('studymark_comment',$data); }
    protected function after_execute() { $this->add_related_files('mod_studymark','documents',null); }
}
