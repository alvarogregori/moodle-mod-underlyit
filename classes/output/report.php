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

namespace mod_underlyit\output;

use renderable;
use renderer_base;
use templatable;

/**
 * Highlighting report rendered with the mod_underlyit/report template.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report implements renderable, templatable {
    /** @var array Report data from \mod_underlyit\local\report::calculate(). */
    protected array $data;

    /**
     * Constructor.
     *
     * @param array $data Report data from \mod_underlyit\local\report::calculate().
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Formats a percentage with one decimal.
     *
     * @param float $value Percentage.
     * @return string
     */
    protected static function percent(float $value): string {
        return format_float($value, 1) . '%';
    }

    /**
     * Colour band of a value.
     *
     * @param float $value Percentage.
     * @param bool $progress Whether the value is reading progress, which uses higher bands.
     * @return string Bootstrap state.
     */
    protected static function state(float $value, bool $progress = false): string {
        [$good, $fair] = $progress ? [75, 40] : [50, 25];
        return $value >= $good ? 'success' : ($value >= $fair ? 'warning' : 'danger');
    }

    /**
     * Context of a metric bar.
     *
     * @param string $label Metric name.
     * @param float $value Percentage.
     * @param float $difference Difference from the course average.
     * @param bool $progress Whether the value is reading progress.
     * @return array
     */
    protected static function metric(string $label, float $value, float $difference, bool $progress = false): array {
        $value = max(0.0, min(100.0, $value));
        return [
            'label' => $label,
            'value' => self::percent($value),
            'width' => round($value, 1),
            'state' => self::state($value, $progress),
            'difference' => self::difference($difference),
        ];
    }

    /**
     * Context of a difference from the course average.
     *
     * @param float $value Difference in percentage points.
     * @return array
     */
    protected static function difference(float $value): array {
        if ($value > 0) {
            return ['arrow' => '↑', 'value' => '+' . self::percent($value), 'class' => 'text-success'];
        }
        if ($value < 0) {
            return ['arrow' => '↓', 'value' => self::percent($value), 'class' => 'text-danger'];
        }
        return ['arrow' => '=', 'value' => self::percent(0), 'class' => 'text-muted'];
    }

    /**
     * Overall status of a learner.
     *
     * @param array $student Student row.
     * @return array
     */
    protected static function status(array $student): array {
        if (empty($student['highlightcount'])) {
            return ['state' => 'secondary', 'label' => 'statusnodata', 'text' => 'interpretationnone'];
        }
        $score = (float)$student['f1score'];
        if ($score >= 50) {
            return ['state' => 'success', 'label' => 'statusgood', 'text' => 'interpretationhigh'];
        }
        if ($score >= 25) {
            return ['state' => 'warning', 'label' => 'statusdeveloping', 'text' => 'interpretationmedium'];
        }
        return ['state' => 'danger', 'label' => 'statusreview', 'text' => 'interpretationlow'];
    }

    /**
     * Exports the data for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = $this->data;
        $hasreference = !empty($data['hasreference']);
        $students = $data['students'];
        usort($students, fn($a, $b) => $b['f1score'] <=> $a['f1score']);

        $rows = [];
        foreach ($students as $student) {
            $status = self::status($student);
            $row = [
                'fullname' => $student['fullname'],
                'highlightcount' => (int)$student['highlightcount'],
                'statusstate' => $status['state'],
                'statuslabel' => get_string($status['label'], 'underlyit'),
                'statustext' => get_string($status['text'], 'underlyit'),
                'progress' => self::metric(
                    get_string('readingreached', 'underlyit'),
                    $student['progress'],
                    $student['progressdifference'],
                    true
                ),
                'detail' => self::metric(
                    get_string('detaillevel', 'underlyit'),
                    $student['detail'],
                    $student['differencefromaverage']
                ),
            ];
            if ($hasreference) {
                $matched = (int)$student['intersectionchars'];
                $extra = (int)$student['studentonlychars'];
                $missing = (int)$student['referenceonlychars'];
                $total = max(1, $matched + $extra + $missing);
                $row['precision'] = self::metric(
                    get_string('precision', 'underlyit'),
                    $student['precision'],
                    $student['precisiondifference']
                );
                $row['recall'] = self::metric(
                    get_string('recall', 'underlyit'),
                    $student['recall'],
                    $student['recalldifference']
                );
                $row['f1score'] = self::metric(
                    get_string('f1score', 'underlyit'),
                    $student['f1score'],
                    $student['f1difference']
                );
                $row['match'] = [
                    'value' => self::percent($student['referenceoverlap']),
                    'state' => self::state($student['referenceoverlap']),
                    'matched' => $matched,
                    'extra' => $extra,
                    'missing' => $missing,
                    'matchedwidth' => round(100 * $matched / $total, 1),
                    'extrawidth' => round(100 * $extra / $total, 1),
                    'missingwidth' => round(100 * $missing / $total, 1),
                    'difference' => self::difference($student['jaccarddifference']),
                ];
            }
            $rows[] = $row;
        }

        return [
            'hasreference' => $hasreference,
            'referencenote' => $hasreference
                ? get_string('referencesummary', 'underlyit', $data['referencechars'])
                : get_string('noreferencehelp', 'underlyit'),
            'courseaverage' => self::percent($data['courseaverage']),
            'documentlength' => get_string('charactercount', 'underlyit', $data['documentchars']),
            'students' => $rows,
            'hasstudents' => !empty($rows),
        ];
    }
}
