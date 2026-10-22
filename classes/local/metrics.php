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

final class metrics {
    public static function user_metrics(int $documentid, int $userid, int $totalchars, \context_module $context): array {
        global $DB;
        $totalchars = max(1, $totalchars);
        $records = $DB->get_records('studymark_highlight', ['documentid' => $documentid], 'userid, id', 'id,userid,selectorjson,reference');
        $student = [];
        $reference = [];
        foreach ($records as $record) {
            $selector = json_decode($record->selectorjson ?? '', true);
            if (($selector['type'] ?? '') !== 'text-offset') {
                continue;
            }
            $start = max(0, min($totalchars, (int)($selector['start'] ?? 0)));
            $end = max(0, min($totalchars, (int)($selector['end'] ?? 0)));
            if ($end <= $start) {
                continue;
            }
            if ((int)$record->userid === $userid) {
                $student[] = [$start, $end];
            }
            if (!empty($record->reference)) {
                $reference[] = [$start, $end];
            }
        }
        $student = self::merge_intervals($student);
        $reference = self::merge_intervals($reference);
        $comparison = self::comparison_scores($student, $reference);
        return [
            'progress' => round(100 * ($student ? max(array_column($student, 1)) : 0) / $totalchars, 1),
            'match' => round($comparison['jaccard'], 1),
            'hasreference' => !empty($reference),
        ];
    }

    public static function merge_intervals(array $intervals): array {
        if (!$intervals) { return []; }
        usort($intervals, static fn($a, $b) => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]));
        $merged = [array_shift($intervals)];
        foreach ($intervals as $interval) {
            $last = count($merged) - 1;
            if ($interval[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $interval[1]);
            } else {
                $merged[] = $interval;
            }
        }
        return $merged;
    }

    public static function length(array $intervals): int {
        return array_sum(array_map(static fn($interval) => $interval[1] - $interval[0], $intervals));
    }

    public static function intersection_length(array $left, array $right): int {
        $length = 0; $i = 0; $j = 0;
        while ($i < count($left) && $j < count($right)) {
            $length += max(0, min($left[$i][1], $right[$j][1]) - max($left[$i][0], $right[$j][0]));
            if ($left[$i][1] < $right[$j][1]) { $i++; } else { $j++; }
        }
        return $length;
    }

    public static function comparison_scores(array $student, array $reference): array {
        $student = self::merge_intervals($student);
        $reference = self::merge_intervals($reference);
        $studentchars = self::length($student);
        $referencechars = self::length($reference);
        $intersection = self::intersection_length($student, $reference);
        $precision = $studentchars ? 100 * $intersection / $studentchars : 0.0;
        $recall = $referencechars ? 100 * $intersection / $referencechars : 0.0;
        $f1score = ($precision + $recall) > 0
            ? 2 * $precision * $recall / ($precision + $recall)
            : 0.0;
        $union = $studentchars + $referencechars - $intersection;

        return [
            'studentchars' => $studentchars,
            'referencechars' => $referencechars,
            'intersection' => $intersection,
            'precision' => $precision,
            'recall' => $recall,
            'f1score' => $f1score,
            'jaccard' => $union ? 100 * $intersection / $union : 0.0,
        ];
    }
}
