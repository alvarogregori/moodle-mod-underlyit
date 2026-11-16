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

namespace mod_underlyit\task;

use mod_underlyit\local\completion;

/**
 * Recalculates every learner's completion after the tutor reference changes.
 *
 * Custom data: cmid, the course module of the activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_completion extends \core\task\adhoc_task {
    /**
     * Name shown in the task logs.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskrefreshcompletion', 'underlyit');
    }

    /**
     * Runs the recalculation. Does nothing if the activity was deleted meanwhile.
     *
     * @return void
     */
    public function execute() {
        $data = $this->get_custom_data();
        $cm = get_coursemodule_from_id('underlyit', (int)($data->cmid ?? 0), 0, false, IGNORE_MISSING);
        if (!$cm) {
            mtrace('The Underly.it activity no longer exists; nothing to recalculate.');
            return;
        }
        $count = completion::refresh_all($cm, \context_module::instance($cm->id));
        mtrace("Recalculated the completion of {$count} learners in course module {$cm->id}.");
    }
}
