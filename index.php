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
 * Underly.it activity plugin.
 *
 * @package    mod_studymark
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);
$PAGE->set_url('/mod/studymark/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'studymark'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
$instances = get_all_instances_in_course('studymark', $course);
echo $OUTPUT->heading(get_string('modulenameplural', 'studymark'));
foreach ($instances as $instance) {
    echo html_writer::link(new moodle_url('/mod/studymark/view.php', ['id' => $instance->coursemodule]), format_string($instance->name), ['class' => 'd-block mb-2']);
}
echo $OUTPUT->footer();
