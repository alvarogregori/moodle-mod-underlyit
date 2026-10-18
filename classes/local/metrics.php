<?php
namespace mod_studymark\local;

defined('MOODLE_INTERNAL') || die();

final class metrics {
    public static function user_metrics(int $documentid, int $userid, int $totalchars, \context_module $context): array {
        global $DB;
        $totalchars = max(1, $totalchars);
        $records = $DB->get_records('studymark_highlight', ['documentid' => $documentid], 'userid, id', 'id,userid,selectorjson');
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
            if (has_capability('mod/studymark:manageannotations', $context, $record->userid)) {
                $reference[] = [$start, $end];
            }
        }
        $student = self::merge($student);
        $reference = self::merge($reference);
        $covered = self::length($student);
        $intersection = self::intersection($student, $reference);
        $union = $covered + self::length($reference) - $intersection;
        return [
            'progress' => round(100 * ($student ? max(array_column($student, 1)) : 0) / $totalchars, 1),
            'match' => $reference && $union ? round(100 * $intersection / $union, 1) : 0.0,
            'hasreference' => !empty($reference),
        ];
    }

    private static function merge(array $intervals): array {
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

    private static function length(array $intervals): int {
        return array_sum(array_map(static fn($interval) => $interval[1] - $interval[0], $intervals));
    }

    private static function intersection(array $left, array $right): int {
        $length = 0; $i = 0; $j = 0;
        while ($i < count($left) && $j < count($right)) {
            $length += max(0, min($left[$i][1], $right[$j][1]) - max($left[$i][0], $right[$j][0]));
            if ($left[$i][1] < $right[$j][1]) { $i++; } else { $j++; }
        }
        return $length;
    }
}
