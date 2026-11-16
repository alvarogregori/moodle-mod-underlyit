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
 * Restore task for the Underly.it activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/restore_underlyit_stepslib.php');

/**
 * Provides the steps to restore an Underly.it activity.
 */
class restore_underlyit_activity_task extends restore_activity_task {
    /**
     * No activity-specific settings.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Adds the structure step.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_underlyit_activity_structure_step('underlyit_structure', 'underlyit.xml'));
    }

    /**
     * Defines the contents whose links must be decoded.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('underlyit', ['intro'], 'underlyit')];
    }

    /**
     * Defines the rules that decode the links encoded on backup.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('UNDERLYITVIEWBYID', '/mod/underlyit/view.php?id=$1', 'course_module'),
            new restore_decode_rule('UNDERLYITINDEX', '/mod/underlyit/index.php?id=$1', 'course'),
        ];
    }
}
