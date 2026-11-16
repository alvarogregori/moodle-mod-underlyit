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

namespace mod_underlyit\completion;

use core_completion\activity_custom_completion;
use mod_underlyit\local\metrics;

/**
 * Custom completion rules of the Underly.it activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class custom_completion extends activity_custom_completion {
    /**
     * Returns the completion state of a rule for the user.
     *
     * @param string $rule Rule name.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        global $DB;
        $activity = $DB->get_record('underlyit', ['id' => $this->cm->instance], '*', MUST_EXIST);
        if (!in_array($rule, self::get_defined_custom_rules(), true)) {
            throw new \coding_exception('Invalid completion rule: ' . $rule);
        }
        $document = $DB->get_record('underlyit_document', ['underlyitid' => $activity->id]);
        if (!$document || empty($document->textlength)) {
            return COMPLETION_INCOMPLETE;
        }
        $context = \context_module::instance($this->cm->id);
        $values = metrics::user_metrics($document->id, $this->userid, $document->textlength, $context);
        if ($rule === 'completionprogress') {
            return $values['progress'] >= (int)$activity->completionprogress ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }
        return $values['hasreference'] && $values['match'] >= (int)$activity->completionmatch
            ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Lists the custom rules.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return ['completionprogress', 'completionmatch'];
    }

    /**
     * Describes the rules for the activity information.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;
        $activity = $DB->get_record(
            'underlyit',
            ['id' => $this->cm->instance],
            'completionprogress, completionmatch',
            MUST_EXIST
        );
        return [
            'completionprogress' => get_string('completionprogressdesc', 'underlyit', $activity->completionprogress),
            'completionmatch' => get_string('completionmatchdesc', 'underlyit', $activity->completionmatch),
        ];
    }

    /**
     * Order in which the rules are displayed.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return ['completionprogress', 'completionmatch'];
    }
}
