<?php
namespace mod_studymark\event;
defined('MOODLE_INTERNAL') || die();
class highlight_created extends \core\event\base {
    protected function init() { $this->data['crud']='c'; $this->data['edulevel']=self::LEVEL_PARTICIPATING; $this->data['objecttable']='studymark_highlight'; }
    public static function get_name() { return get_string('eventhighlightcreated', 'studymark'); }
    public function get_description() { return "The user with id '{$this->userid}' created highlight '{$this->objectid}'."; }
}
