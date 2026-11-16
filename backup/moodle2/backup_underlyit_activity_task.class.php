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
 * Backup task for the Underly.it activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/backup_underlyit_stepslib.php');

/**
 * Provides the steps to back up an Underly.it activity.
 */
class backup_underlyit_activity_task extends backup_activity_task {
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
        $this->add_step(new backup_underlyit_activity_structure_step('underlyit_structure', 'underlyit.xml'));
    }

    /**
     * Encodes links to the activity so that they can be restored elsewhere.
     *
     * @param string $content Content that may contain links.
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot . '/mod/underlyit', '#');
        $content = preg_replace('#(' . $base . '/index\.php\?id=)([0-9]+)#', '$@UNDERLYITINDEX*$2@$', $content);
        return preg_replace('#(' . $base . '/view\.php\?id=)([0-9]+)#', '$@UNDERLYITVIEWBYID*$2@$', $content);
    }
}
