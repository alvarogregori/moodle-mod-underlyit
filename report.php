<?php
require('../../config.php');
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('studymark', $id, 0, false, MUST_EXIST);
$activity = $DB->get_record('studymark', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/studymark:manageannotations', $context);
$PAGE->set_url('/mod/studymark/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('analytics', 'studymark'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js(new moodle_url('/mod/studymark/thirdparty/pdfjs/pdf.min.js'), true);
$PAGE->requires->js(new moodle_url('/mod/studymark/thirdparty/jszip/jszip.min.js'), true);
$PAGE->requires->js_call_amd('mod_studymark/analytics', 'init', [[
    'cmid' => (int)$cm->id,
    'pdfworkerurl' => (new moodle_url('/mod/studymark/thirdparty/pdfjs/pdf.worker.min.js'))->out(false),
]]);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('analyticstitle', 'studymark', format_string($activity->name)));
echo html_writer::div('', 'mod-studymark-analytics', ['id' => 'studymark-analytics']);
echo $OUTPUT->footer();
