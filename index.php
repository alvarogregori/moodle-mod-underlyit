<?php
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
