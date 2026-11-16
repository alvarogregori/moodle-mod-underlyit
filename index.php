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
 * Lists the Underly.it activities in a course.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

\mod_underlyit\event\course_module_instance_list_viewed::create([
    'context' => context_course::instance($course->id),
])->trigger();

$PAGE->set_url('/mod/underlyit/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'underlyit'));
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'underlyit'));
foreach (get_all_instances_in_course('underlyit', $course) as $instance) {
    echo html_writer::link(
        new moodle_url('/mod/underlyit/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name),
        ['class' => 'd-block mb-2']
    );
}
echo $OUTPUT->footer();
