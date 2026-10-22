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
require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_studymark_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('studymarkname', 'studymark'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();
        $mform->addElement('filemanager', 'documents', get_string('documents', 'studymark'), null, [
            'subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1,
            'accepted_types' => ['.pdf', '.epub', '.docx', '.html', '.htm', '.txt'],
        ]);
        $mform->addRule('documents', null, 'required', null, 'client');
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function add_completion_rules() {
        $options = [0 => get_string('completiondisabled', 'studymark')];
        foreach ([10, 20, 25, 30, 40, 50, 60, 70, 75, 80, 90, 100] as $percentage) {
            $options[$percentage] = get_string('completionpercentage', 'studymark', $percentage);
        }
        $this->_form->addElement('select', 'completionprogress', get_string('completionprogress', 'studymark'), $options);
        $this->_form->addHelpButton('completionprogress', 'completionprogress', 'studymark');
        $this->_form->setDefault('completionprogress', 0);
        $this->_form->addElement('select', 'completionmatch', get_string('completionmatch', 'studymark'), $options);
        $this->_form->addHelpButton('completionmatch', 'completionmatch', 'studymark');
        $this->_form->setDefault('completionmatch', 0);
        return ['completionprogress', 'completionmatch'];
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completionprogress']) || !empty($data['completionmatch']);
    }
    public function data_preprocessing(&$defaultvalues) {
        if (!empty($this->current->instance)) {
            $draftitemid = file_get_submitted_draft_itemid('documents');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_studymark', 'documents', 0, [
                'subdirs' => 0, 'maxfiles' => 1,
            ]);
            $defaultvalues['documents'] = $draftitemid;
        }
    }
}
