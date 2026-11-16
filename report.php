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
 * Displays the highlighting report of an activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('underlyit', $id, 0, false, MUST_EXIST);
$activity = $DB->get_record('underlyit', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/underlyit:manageannotations', $context);
$PAGE->set_url('/mod/underlyit/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('analytics', 'underlyit'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('analyticstitle', 'underlyit', format_string($activity->name)));
$document = $DB->get_record('underlyit_document', ['underlyitid' => $activity->id]);
if (!$document) {
    echo $OUTPUT->notification(get_string('nodocument', 'underlyit'), \core\output\notification::NOTIFY_INFO);
} else if (empty($document->textlength)) {
    echo $OUTPUT->notification(get_string('documentnotmeasured', 'underlyit'), \core\output\notification::NOTIFY_WARNING);
} else {
    $report = new \mod_underlyit\output\report(\mod_underlyit\local\report::calculate($document, $context));
    echo $OUTPUT->render_from_template('mod_underlyit/report', $report->export_for_template($OUTPUT));
}
echo $OUTPUT->footer();
