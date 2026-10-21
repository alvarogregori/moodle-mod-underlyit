<?php
namespace mod_studymark;

defined('MOODLE_INTERNAL') || die();

final class metrics_test extends \advanced_testcase {
    public function test_interval_operations_are_deterministic(): void {
        $merged = \mod_studymark\local\metrics::merge_intervals([[5, 9], [1, 4], [3, 7], [12, 13]]);
        $this->assertSame([[1, 9], [12, 13]], $merged);
        $this->assertSame(9, \mod_studymark\local\metrics::length($merged));
        $this->assertSame(4, \mod_studymark\local\metrics::intersection_length($merged, [[0, 3], [7, 10]]));
    }

    public function test_comparison_scores(): void {
        $scores = \mod_studymark\local\metrics::comparison_scores([[0, 10]], [[5, 15]]);
        $this->assertSame(10, $scores['studentchars']);
        $this->assertSame(10, $scores['referencechars']);
        $this->assertSame(5, $scores['intersection']);
        $this->assertEqualsWithDelta(50.0, $scores['precision'], 0.001);
        $this->assertEqualsWithDelta(50.0, $scores['recall'], 0.001);
        $this->assertEqualsWithDelta(50.0, $scores['f1score'], 0.001);
        $this->assertEqualsWithDelta(33.333, $scores['jaccard'], 0.001);
    }

    public function test_comparison_scores_handle_empty_and_overlapping_intervals(): void {
        $empty = \mod_studymark\local\metrics::comparison_scores([], []);
        $this->assertSame(0.0, $empty['precision']);
        $this->assertSame(0.0, $empty['recall']);
        $this->assertSame(0.0, $empty['f1score']);

        $exact = \mod_studymark\local\metrics::comparison_scores([[0, 5], [3, 10]], [[0, 10]]);
        $this->assertEqualsWithDelta(100.0, $exact['precision'], 0.001);
        $this->assertEqualsWithDelta(100.0, $exact['recall'], 0.001);
        $this->assertEqualsWithDelta(100.0, $exact['f1score'], 0.001);
        $this->assertEqualsWithDelta(100.0, $exact['jaccard'], 0.001);
    }
}
