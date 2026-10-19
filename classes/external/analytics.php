<?php
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
            $intersection = metrics::intersection_length($intervals, $reference);
            $union = $covered + metrics::length($reference) - $intersection;
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
            'referencechars' => metrics::length($reference),
            'hasreference' => !empty($reference),
            'courseaverage' => round($average, 1),
            'students' => $rows,
        ];
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
