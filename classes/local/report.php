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
 * Calculates the highlighting report of an activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report {
    /**
     * Calculates each learner's metrics and the course averages.
     *
     * Users who can manage annotations (tutors) are left out of the report.
     *
     * @param \stdClass $document Document record.
     * @param \context_module $context Module context.
     * @return array With documentchars, referencechars, hasreference, courseaverage, courseaverages and students.
     */
    public static function calculate(\stdClass $document, \context_module $context): array {
        global $DB;
        $totalchars = max(1, (int)$document->textlength);
        $records = $DB->get_records('underlyit_highlight', ['documentid' => $document->id], 'userid, id');
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
                if (!empty($record->reference)) {
                    $reference[] = [$start, $end];
                }
            }
        }
        foreach ($byuser as $userid => $intervals) {
            $byuser[$userid] = metrics::merge_intervals($intervals);
        }
        $reference = metrics::merge_intervals($reference);
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $participants = get_enrolled_users(
            $context,
            'mod/underlyit:addhighlight',
            0,
            'u.id, ' . $namefields,
            'u.lastname, u.firstname'
        );
        $rows = [];
        foreach ($participants as $participant) {
            if (has_capability('mod/underlyit:manageannotations', $context, $participant->id)) {
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
}
