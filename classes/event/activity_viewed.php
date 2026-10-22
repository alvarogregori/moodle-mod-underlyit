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
 * StudyMark activity plugin.
 *
 * @package    mod_studymark
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_studymark\event;
defined('MOODLE_INTERNAL') || die();
class activity_viewed extends \core\event\course_module_viewed {
    protected function init() { parent::init(); $this->data['objecttable'] = 'studymark'; }
    public static function get_name() { return get_string('eventactivityviewed', 'studymark'); }
    public function get_description() { return "The user with id '{$this->userid}' viewed StudyMark activity '{$this->objectid}'."; }
}
