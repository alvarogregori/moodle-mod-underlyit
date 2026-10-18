<?php
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/restore_studymark_stepslib.php');
class restore_studymark_activity_task extends restore_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() { $this->add_step(new restore_studymark_activity_structure_step('studymark_structure','studymark.xml')); }
    public static function define_decode_contents() { return [new restore_decode_content('studymark',['intro'],'studymark')]; }
    public static function define_decode_rules() { return []; }
}
