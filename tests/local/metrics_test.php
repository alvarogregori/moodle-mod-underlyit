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
 * Unit tests for the highlighting metrics.
 *
 * @package    mod_underlyit
 * @category   test
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_underlyit\local\metrics
 */
final class metrics_test extends \basic_testcase {
    /**
     * Merging, length and intersection of intervals.
     *
     * @return void
     */
    public function test_interval_operations_are_deterministic(): void {
        $merged = metrics::merge_intervals([[5, 9], [1, 4], [3, 7], [12, 13]]);
        $this->assertSame([[1, 9], [12, 13]], $merged);
        $this->assertSame(9, metrics::length($merged));
        $this->assertSame(4, metrics::intersection_length($merged, [[0, 3], [7, 10]]));
    }

    /**
     * Precision, recall, F1 and Jaccard of a partial overlap.
     *
     * @return void
     */
    public function test_comparison_scores(): void {
        $scores = metrics::comparison_scores([[0, 10]], [[5, 15]]);
        $this->assertSame(10, $scores['studentchars']);
        $this->assertSame(10, $scores['referencechars']);
        $this->assertSame(5, $scores['intersection']);
        $this->assertEqualsWithDelta(50.0, $scores['precision'], 0.001);
        $this->assertEqualsWithDelta(50.0, $scores['recall'], 0.001);
        $this->assertEqualsWithDelta(50.0, $scores['f1score'], 0.001);
        $this->assertEqualsWithDelta(33.333, $scores['jaccard'], 0.001);
    }

    /**
     * Empty input and overlapping learner intervals.
     *
     * @return void
     */
    public function test_comparison_scores_handle_empty_and_overlapping_intervals(): void {
        $empty = metrics::comparison_scores([], []);
        $this->assertSame(0.0, $empty['precision']);
        $this->assertSame(0.0, $empty['recall']);
        $this->assertSame(0.0, $empty['f1score']);

        $exact = metrics::comparison_scores([[0, 5], [3, 10]], [[0, 10]]);
        $this->assertEqualsWithDelta(100.0, $exact['precision'], 0.001);
        $this->assertEqualsWithDelta(100.0, $exact['recall'], 0.001);
        $this->assertEqualsWithDelta(100.0, $exact['f1score'], 0.001);
        $this->assertEqualsWithDelta(100.0, $exact['jaccard'], 0.001);
    }
}
