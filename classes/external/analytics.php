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
 * Underly.it activity plugin.
 *
 * @package    mod_studymark
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_studymark\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_studymark\local\access;
use mod_studymark\local\metrics;

final class analytics extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'documentid' => new external_value(PARAM_INT, 'Document ID'),
        ]);
    }

    public static function execute(int $cmid, int $documentid): array {
        global $DB;
        [$cm, , $context, $document] = access::document($cmid, $documentid);
        require_capability('mod/studymark:manageannotations', $context);
        $totalchars = max(1, (int)$document->textlength);
        $records = $DB->get_records('studymark_highlight', ['documentid' => $documentid], 'userid, id');
        $byuser = [];
        $counts = [];
        $reference = [];
        foreach ($records as $record) {
            $counts[$record->userid] = ($counts[$record->userid] ?? 0) + 1;
            $selector = json_decode($record->selectorjson ?? '', true);
            if (($selector['type'] ?? '') !== 'text-offset') {
                continue;
            }
            $start = max(0, min($totalchars, (int)($selector['start'] ?? 0)));
            $end = max(0, min($totalchars, (int)($selector['end'] ?? 0)));
            if ($end > $start) {
                $byuser[$record->userid][] = [$start, $end];
                if (!empty($record->reference)) { $reference[] = [$start, $end]; }
            }
        }
        foreach ($byuser as $userid => $intervals) {
            $byuser[$userid] = metrics::merge_intervals($intervals);
        }
        $reference = metrics::merge_intervals($reference);
        $participants = get_enrolled_users($context, 'mod/studymark:addhighlight', 0, 'u.id,u.firstname,u.lastname', 'u.lastname,u.firstname');
        $rows = [];
        foreach ($participants as $participant) {
            if (has_capability('mod/studymark:manageannotations', $context, $participant->id)) {
                continue;
            }
            $intervals = $byuser[$participant->id] ?? [];
            $covered = metrics::length($intervals);
            $furthest = $intervals ? max(array_column($intervals, 1)) : 0;
            $comparison = metrics::comparison_scores($intervals, $reference);
            $rows[] = [
                'userid' => (int)$participant->id,
                'fullname' => fullname($participant),
                'highlightcount' => (int)($counts[$participant->id] ?? 0),
                'progress' => round(100 * $furthest / $totalchars, 1),
                'detail' => round(100 * $covered / $totalchars, 1),
                'differencefromaverage' => 0.0,
                'progressdifference' => 0.0,
                'precisiondifference' => 0.0,
                'recalldifference' => 0.0,
                'f1difference' => 0.0,
                'jaccarddifference' => 0.0,
                'precision' => round($comparison['precision'], 1),
                'recall' => round($comparison['recall'], 1),
                'f1score' => round($comparison['f1score'], 1),
                'referenceoverlap' => round($comparison['jaccard'], 1),
                'intersectionchars' => $comparison['intersection'],
                'studentonlychars' => $comparison['studentchars'] - $comparison['intersection'],
                'referenceonlychars' => $comparison['referencechars'] - $comparison['intersection'],
            ];
        }
        $metricmap = [
            'progress' => 'progressdifference',
            'detail' => 'differencefromaverage',
            'precision' => 'precisiondifference',
            'recall' => 'recalldifference',
            'f1score' => 'f1difference',
            'referenceoverlap' => 'jaccarddifference',
        ];
        $averages = [];
        foreach ($metricmap as $metric => $difference) {
            $averages[$metric] = $rows
                ? round(array_sum(array_column($rows, $metric)) / count($rows), 1)
                : 0.0;
        }
        foreach ($rows as &$row) {
            foreach ($metricmap as $metric => $difference) {
                $row[$difference] = round($row[$metric] - $averages[$metric], 1);
            }
        }
        unset($row);
        return [
            'documentchars' => $totalchars,
            'referencechars' => metrics::length($reference),
            'hasreference' => !empty($reference),
            'courseaverage' => $averages['detail'],
            'courseaverages' => $averages,
            'students' => $rows,
        ];
    }

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
