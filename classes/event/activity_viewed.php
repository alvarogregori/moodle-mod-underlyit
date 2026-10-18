<?php
namespace mod_studymark\event;
defined('MOODLE_INTERNAL') || die();
class activity_viewed extends \core\event\course_module_viewed {
    protected function init() { parent::init(); $this->data['objecttable'] = 'studymark'; }
    public static function get_name() { return get_string('eventactivityviewed', 'studymark'); }
    public function get_description() { return "The user with id '{$this->userid}' viewed StudyMark activity '{$this->objectid}'."; }
}
