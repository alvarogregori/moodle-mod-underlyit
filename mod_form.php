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
 * Activity settings form.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/underlyit/lib.php');

/**
 * Settings form for an Underly.it activity.
 */
class mod_underlyit_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('underlyitname', 'underlyit'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();
        $mform->addElement('filemanager', 'documents', get_string('documents', 'underlyit'), null, [
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 1,
            'accepted_types' => UNDERLYIT_ACCEPTED_TYPES,
        ]);
        $mform->addHelpButton('documents', 'documents', 'underlyit');
        $mform->addRule('documents', null, 'required', null, 'client');
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds the custom completion rules.
     *
     * @return string[] Names of the added elements.
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();
        $options = [0 => get_string('completiondisabled', 'underlyit')];
        foreach ([10, 20, 25, 30, 40, 50, 60, 70, 75, 80, 90, 100] as $percentage) {
            $options[$percentage] = get_string('completionpercentage', 'underlyit', $percentage);
        }
        $names = [];
        foreach (['completionprogress', 'completionmatch'] as $rule) {
            $name = $rule . $suffix;
            $mform->addElement('select', $name, get_string($rule, 'underlyit'), $options);
            $mform->addHelpButton($name, $rule, 'underlyit');
            $mform->setDefault($name, 0);
            $names[] = $name;
        }
        return $names;
    }

    /**
     * Whether any custom completion rule is enabled.
     *
     * @param array $data Submitted form data.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionprogress' . $suffix]) || !empty($data['completionmatch' . $suffix]);
    }

    /**
     * Prepares the document file manager when editing an existing activity.
     *
     * @param array $defaultvalues Form defaults, passed by reference.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        if (!empty($this->current->instance)) {
            $draftitemid = file_get_submitted_draft_itemid('documents');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_underlyit', 'documents', 0, [
                'subdirs' => 0,
                'maxfiles' => 1,
            ]);
            $defaultvalues['documents'] = $draftitemid;
        }
    }
}
