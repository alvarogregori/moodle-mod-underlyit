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
namespace mod_studymark\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_studymark\local\access;

final class update_highlight extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'highlightid' => new external_value(PARAM_INT, 'Highlight ID'),
            'color' => new external_value(PARAM_TEXT, 'Colour'),
            'note' => new external_value(PARAM_RAW, 'Note'),
            'shared' => new external_value(PARAM_BOOL, 'Shared'),
            'groupid' => new external_value(PARAM_INT, 'Moodle group ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $cmid, int $highlightid, string $color, string $note, bool $shared, int $groupid = 0): array {
        global $DB, $USER;
        [$cm, $activity, $context] = access::module($cmid);
        $record = $DB->get_record_sql(
            'SELECT h.* FROM {studymark_highlight} h JOIN {studymark_document} d ON d.id=h.documentid WHERE h.id=? AND d.studymarkid=?',
            [$highlightid, $activity->id], MUST_EXIST
        );
        if ($record->userid != $USER->id && !has_capability('mod/studymark:manageannotations', $context)) {
            throw new \required_capability_exception($context, 'mod/studymark:manageannotations', 'nopermissions', '');
        }
        if ($shared) {
            require_capability('mod/studymark:sharehighlight', $context);
            $groupid = access::active_group($cm, $groupid);
        } else {
            $groupid = 0;
        }
        if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            throw new \invalid_parameter_exception('Invalid highlight colour');
        }
        $record->color = strtolower($color);
        $record->note = $note;
        $record->shared = (int) $shared;
        $record->groupid = $groupid;
        $record->timemodified = time();
        $DB->update_record('studymark_highlight', $record);
        return ['id' => $record->id, 'color' => $record->color, 'note' => $record->note, 'shared' => (bool) $record->shared, 'groupid' => $record->groupid];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Highlight ID'),
            'color' => new external_value(PARAM_TEXT, 'Colour'),
            'note' => new external_value(PARAM_RAW, 'Note'),
            'shared' => new external_value(PARAM_BOOL, 'Shared'),
            'groupid' => new external_value(PARAM_INT, 'Moodle group ID'),
        ]);
    }
}
