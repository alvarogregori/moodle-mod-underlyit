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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_underlyit\local\access;
use mod_underlyit\local\report;

/**
 * External function returning the highlighting report of an activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class analytics extends external_api {
    /**
     * Parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'documentid' => new external_value(PARAM_INT, 'Document ID'),
        ]);
    }

    /**
     * Calculates the per-student metrics against the tutor reference.
     *
     * @param int $cmid Course module ID.
     * @param int $documentid Document ID.
     * @return array
     */
    public static function execute(int $cmid, int $documentid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'documentid' => $documentid,
        ]);
        [, , $context, $document] = access::document($params['cmid'], $params['documentid']);
        require_capability('mod/underlyit:manageannotations', $context);
        return report::calculate($document, $context);
    }

    /**
     * Return value of the function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'documentchars' => new external_value(PARAM_INT, 'Document characters'),
            'referencechars' => new external_value(PARAM_INT, 'Reference highlighted characters'),
            'hasreference' => new external_value(PARAM_BOOL, 'Whether a reference exists'),
            'courseaverage' => new external_value(PARAM_FLOAT, 'Course mean detail percentage'),
            'courseaverages' => new external_single_structure([
                'progress' => new external_value(PARAM_FLOAT, 'Course mean reached percentage'),
                'detail' => new external_value(PARAM_FLOAT, 'Course mean detail percentage'),
                'precision' => new external_value(PARAM_FLOAT, 'Course mean precision'),
                'recall' => new external_value(PARAM_FLOAT, 'Course mean recall'),
                'f1score' => new external_value(PARAM_FLOAT, 'Course mean F1-score'),
                'referenceoverlap' => new external_value(PARAM_FLOAT, 'Course mean Jaccard overlap'),
            ]),
            'students' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'highlightcount' => new external_value(PARAM_INT, 'Highlight count'),
                'progress' => new external_value(PARAM_FLOAT, 'Reached percentage'),
                'detail' => new external_value(PARAM_FLOAT, 'Highlighted percentage'),
                'differencefromaverage' => new external_value(PARAM_FLOAT, 'Detail difference from course mean'),
                'progressdifference' => new external_value(PARAM_FLOAT, 'Progress difference from course mean'),
                'precisiondifference' => new external_value(PARAM_FLOAT, 'Precision difference from course mean'),
                'recalldifference' => new external_value(PARAM_FLOAT, 'Recall difference from course mean'),
                'f1difference' => new external_value(PARAM_FLOAT, 'F1 difference from course mean'),
                'jaccarddifference' => new external_value(PARAM_FLOAT, 'Jaccard difference from course mean'),
                'precision' => new external_value(PARAM_FLOAT, 'Precision against reference'),
                'recall' => new external_value(PARAM_FLOAT, 'Recall against reference'),
                'f1score' => new external_value(PARAM_FLOAT, 'Harmonic mean of precision and recall'),
                'referenceoverlap' => new external_value(PARAM_FLOAT, 'Jaccard overlap with reference'),
                'intersectionchars' => new external_value(PARAM_INT, 'Characters in the intersection'),
                'studentonlychars' => new external_value(PARAM_INT, 'Student-only highlighted characters'),
                'referenceonlychars' => new external_value(PARAM_INT, 'Reference-only highlighted characters'),
            ])),
        ]);
    }
}
