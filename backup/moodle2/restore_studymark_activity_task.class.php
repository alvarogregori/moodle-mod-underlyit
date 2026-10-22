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
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/restore_studymark_stepslib.php');
class restore_studymark_activity_task extends restore_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() { $this->add_step(new restore_studymark_activity_structure_step('studymark_structure','studymark.xml')); }
    public static function define_decode_contents() { return [new restore_decode_content('studymark',['intro'],'studymark')]; }
    public static function define_decode_rules() { return []; }
}
