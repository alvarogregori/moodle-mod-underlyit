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
$id = optional_param('id', 0, PARAM_INT);
$s = optional_param('s', 0, PARAM_INT);
if ($id) {
    $cm = get_coursemodule_from_id('studymark', $id, 0, false, MUST_EXIST);
    $activity = $DB->get_record('studymark', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $activity = $DB->get_record('studymark', ['id' => $s], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('studymark', $activity->id, $activity->course, false, MUST_EXIST);
}
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/studymark:view', $context);
$PAGE->set_url('/mod/studymark/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->set_attrs(['hidecompletion' => false]);
$PAGE->requires->js(new moodle_url('/mod/studymark/thirdparty/pdfjs/pdf.min.js'), true);
$PAGE->requires->js(new moodle_url('/mod/studymark/thirdparty/jszip/jszip.min.js'), true);
$PAGE->requires->js_call_amd('mod_studymark/app', 'init', [[
    'cmid' => (int) $cm->id,
    'canhighlight' => has_capability('mod/studymark:addhighlight', $context),
    'cancomment' => has_capability('mod/studymark:addcomment', $context),
    'canmanage' => has_capability('mod/studymark:manageannotations', $context),
    'showreference' => !empty($activity->showreference),
    'reporturl' => (new moodle_url('/mod/studymark/report.php', ['id' => $cm->id]))->out(false),
    'pdfworkerurl' => (new moodle_url('/mod/studymark/thirdparty/pdfjs/pdf.worker.min.js'))->out(false),
    'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
]]);
\mod_studymark\event\activity_viewed::create(['objectid' => $activity->id, 'context' => $context])->trigger();
echo $OUTPUT->header();
if (trim($activity->intro)) {
    echo $OUTPUT->box(format_module_intro('studymark', $activity, $cm->id), 'generalbox mod_introbox');
}
echo html_writer::div('', 'mod-studymark-app', ['id' => 'studymark-app', 'data-cmid' => $cm->id]);
echo $OUTPUT->footer();
