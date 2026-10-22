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
namespace mod_studymark\local;

defined('MOODLE_INTERNAL') || die();

final class completion {
    public static function refresh(\stdClass $cm, \context_module $context, int $userid, bool $referencechanged = false): void {
        global $DB;
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $info = new \completion_info($course);
        if (!$info->is_enabled($cm)) { return; }
        if ($referencechanged) {
            foreach (get_enrolled_users($context, 'mod/studymark:addhighlight', 0, 'u.id') as $user) {
                if (!has_capability('mod/studymark:manageannotations', $context, $user->id)) {
                    $info->update_state($cm, COMPLETION_UNKNOWN, $user->id);
                }
            }
        } else {
            $info->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }
}
