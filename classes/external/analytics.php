<?php
namespace mod_studymark\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_studymark\local\access;

final class analytics extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'documentid' => new external_value(PARAM_INT, 'Document ID'),
            'totalchars' => new external_value(PARAM_INT, 'Normalized document character count'),
        ]);
    }

    public static function execute(int $cmid, int $documentid, int $totalchars): array {
        global $DB;
        [$cm, , $context] = access::document($cmid, $documentid);
        require_capability('mod/studymark:manageannotations', $context);
        $totalchars = max(1, $totalchars);
        $DB->set_field('studymark_document', 'textlength', $totalchars, ['id' => $documentid]);
        \mod_studymark\local\completion::refresh($cm, $context, 0, true);
        $records = $DB->get_records('studymark_highlight', ['documentid' => $documentid], 'userid, id');
        $byuser = [];
        $counts = [];
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
            }
        }
        foreach ($byuser as $userid => $intervals) {
            $byuser[$userid] = self::merge_intervals($intervals);
        }
        $reference = [];
        foreach ($byuser as $userid => $intervals) {
            if (has_capability('mod/studymark:manageannotations', $context, $userid)) {
                $reference = array_merge($reference, $intervals);
            }
        }
        $reference = self::merge_intervals($reference);
        $participants = get_enrolled_users($context, 'mod/studymark:addhighlight', 0, 'u.id,u.firstname,u.lastname', 'u.lastname,u.firstname');
        $rows = [];
        foreach ($participants as $participant) {
            if (has_capability('mod/studymark:manageannotations', $context, $participant->id)) {
                continue;
            }
            $intervals = $byuser[$participant->id] ?? [];
            $covered = self::length($intervals);
            $furthest = $intervals ? max(array_column($intervals, 1)) : 0;
            $intersection = self::intersection_length($intervals, $reference);
            $union = $covered + self::length($reference) - $intersection;
            $rows[] = [
                'userid' => (int)$participant->id,
                'fullname' => fullname($participant),
                'highlightcount' => (int)($counts[$participant->id] ?? 0),
                'progress' => round(100 * $furthest / $totalchars, 1),
                'detail' => round(100 * $covered / $totalchars, 1),
                'differencefromaverage' => 0.0,
                'referenceoverlap' => $reference && $union ? round(100 * $intersection / $union, 1) : 0.0,
            ];
        }
        $average = $rows ? array_sum(array_column($rows, 'detail')) / count($rows) : 0.0;
        foreach ($rows as &$row) {
            $row['differencefromaverage'] = round($row['detail'] - $average, 1);
        }
        unset($row);
        return [
            'documentchars' => $totalchars,
            'referencechars' => self::length($reference),
            'hasreference' => !empty($reference),
            'courseaverage' => round($average, 1),
            'students' => $rows,
        ];
    }

    private static function merge_intervals(array $intervals): array {
        if (!$intervals) {
            return [];
        }
        usort($intervals, static fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
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

    private static function length(array $intervals): int {
        return array_sum(array_map(static fn($interval) => $interval[1] - $interval[0], $intervals));
    }

    private static function intersection_length(array $left, array $right): int {
        $length = 0;
        $i = 0;
        $j = 0;
        while ($i < count($left) && $j < count($right)) {
            $length += max(0, min($left[$i][1], $right[$j][1]) - max($left[$i][0], $right[$j][0]));
            if ($left[$i][1] < $right[$j][1]) {
                $i++;
            } else {
                $j++;
            }
        }
        return $length;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'documentchars' => new external_value(PARAM_INT, 'Document characters'),
            'referencechars' => new external_value(PARAM_INT, 'Reference highlighted characters'),
            'hasreference' => new external_value(PARAM_BOOL, 'Whether a reference exists'),
            'courseaverage' => new external_value(PARAM_FLOAT, 'Course mean detail percentage'),
            'students' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'highlightcount' => new external_value(PARAM_INT, 'Highlight count'),
                'progress' => new external_value(PARAM_FLOAT, 'Reached percentage'),
                'detail' => new external_value(PARAM_FLOAT, 'Highlighted percentage'),
                'differencefromaverage' => new external_value(PARAM_FLOAT, 'Difference from course mean'),
                'referenceoverlap' => new external_value(PARAM_FLOAT, 'Jaccard overlap with reference'),
            ])),
        ]);
    }
}
