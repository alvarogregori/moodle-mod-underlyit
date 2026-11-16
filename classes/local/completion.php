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

namespace mod_underlyit\local;

/**
 * Recalculates activity completion after highlighting changes.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class completion {
    /**
     * Marks completion as unknown so that Moodle recalculates it.
     *
     * A change to one learner's highlighting is recalculated at once. A change to the
     * tutor reference or to the document length affects every learner, so it is
     * recalculated by an ad hoc task instead of inside the request.
     *
     * @param \stdClass $cm Course module record.
     * @param \context_module $context Module context.
     * @param int $userid User whose highlighting changed.
     * @param bool $referencechanged Whether the tutor reference changed, which affects every learner.
     * @return void
     */
    public static function refresh(\stdClass $cm, \context_module $context, int $userid, bool $referencechanged = false): void {
        $info = self::info($cm);
        if (!$info) {
            return;
        }
        if ($referencechanged) {
            $task = new \mod_underlyit\task\refresh_completion();
            $task->set_custom_data(['cmid' => (int)$cm->id]);
            // Identical pending tasks are merged, so a tutor highlighting many passages queues one recalculation.
            \core\task\manager::queue_adhoc_task($task, true);
            return;
        }
        $info->update_state($cm, COMPLETION_UNKNOWN, $userid);
    }

    /**
     * Recalculates the completion of every learner of an activity.
     *
     * Tutors (users who can manage annotations) are skipped: their highlighting is the reference.
     *
     * @param \stdClass $cm Course module record.
     * @param \context_module $context Module context.
     * @return int Number of learners recalculated.
     */
    public static function refresh_all(\stdClass $cm, \context_module $context): int {
        $info = self::info($cm);
        if (!$info) {
            return 0;
        }
        $count = 0;
        foreach (get_enrolled_users($context, 'mod/underlyit:addhighlight', 0, 'u.id') as $user) {
            if (!has_capability('mod/underlyit:manageannotations', $context, $user->id)) {
                $info->update_state($cm, COMPLETION_UNKNOWN, $user->id);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Completion information of the course, if completion is enabled for the activity.
     *
     * @param \stdClass $cm Course module record.
     * @return \completion_info|null
     */
    private static function info(\stdClass $cm): ?\completion_info {
        global $DB;
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $info = new \completion_info($course);
        return $info->is_enabled($cm) ? $info : null;
    }
}
