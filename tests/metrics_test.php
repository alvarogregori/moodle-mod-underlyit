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
}
