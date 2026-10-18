<?php
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/backup_studymark_stepslib.php');
class backup_studymark_activity_task extends backup_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() { $this->add_step(new backup_studymark_activity_structure_step('studymark_structure', 'studymark.xml')); }
    public static function encode_content_links($content) { return $content; }
}
