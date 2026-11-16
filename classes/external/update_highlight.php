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

namespace mod_underlyit\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_underlyit\local\access;

/**
 * External function to change the colour of a highlight.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class update_highlight extends external_api {
    /**
     * Parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'highlightid' => new external_value(PARAM_INT, 'Highlight ID'),
            'color' => new external_value(PARAM_TEXT, 'Colour'),
        ]);
    }

    /**
     * Recolours a highlight owned by the user, or any highlight for managers.
     *
     * @param int $cmid Course module ID.
     * @param int $highlightid Highlight ID.
     * @param string $color Colour in #rrggbb form.
     * @return array
     */
    public static function execute(int $cmid, int $highlightid, string $color): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'highlightid' => $highlightid,
            'color' => $color,
        ]);
        [, $activity, $context] = access::module($params['cmid']);
        $record = $DB->get_record_sql(
            "SELECT h.*
               FROM {underlyit_highlight} h
               JOIN {underlyit_document} d ON d.id = h.documentid
              WHERE h.id = :id AND d.underlyitid = :underlyitid",
            ['id' => $params['highlightid'], 'underlyitid' => $activity->id],
            MUST_EXIST
        );
        if ($record->userid != $USER->id) {
            require_capability('mod/underlyit:manageannotations', $context);
        }
        if (!preg_match('/^#[0-9a-f]{6}$/i', $params['color'])) {
            throw new \invalid_parameter_exception('Invalid highlight colour');
        }
        $record->color = strtolower($params['color']);
        $record->timemodified = time();
        $DB->update_record('underlyit_highlight', $record);
        return [
            'id' => (int)$record->id,
            'color' => $record->color,
        ];
    }

    /**
     * Return value of the function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Highlight ID'),
            'color' => new external_value(PARAM_TEXT, 'Colour'),
        ]);
    }
}
