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

namespace mod_underlyit\external;

use core_external\external_api;

/**
 * Tests of the highlight external functions.
 *
 * @package    mod_underlyit
 * @category   test
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_underlyit\external\api
 * @covers     \mod_underlyit\external\update_highlight
 */
final class api_test extends \advanced_testcase {
    /** @var \stdClass Course module. */
    private \stdClass $cm;

    /** @var \stdClass Document record. */
    private \stdClass $document;

    /** @var \stdClass Tutor. */
    private \stdClass $teacher;

    /** @var \stdClass Learner. */
    private \stdClass $student;

    /** @var \stdClass Another learner. */
    private \stdClass $other;

    /**
     * Creates an activity with a document, a tutor and two learners.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('underlyit', ['course' => $course->id]);
        $this->cm = get_coursemodule_from_instance('underlyit', $activity->id, $course->id, false, MUST_EXIST);
        $this->setUser($this->teacher);
        $this->document = $this->getDataGenerator()->get_plugin_generator('mod_underlyit')->create_document($activity, 500);
    }

    /**
     * Creates a highlight through the external function as the current user.
     *
     * @param int $start Start offset.
     * @param int $end End offset.
     * @param array $extra Other parameters.
     * @return array
     */
    private function create(int $start, int $end, array $extra = []): array {
        $p = $extra + [
            'selectedtext' => 'Some text',
            'color' => '#FFF176',
            'positionorder' => $start,
            'reference' => false,
        ];
        $result = api::create_highlight(
            $this->cm->id,
            $this->document->id,
            $p['selectedtext'],
            json_encode(['type' => 'text-offset', 'start' => $start, 'end' => $end]),
            0,
            $p['color'],
            '',
            (float)$p['positionorder'],
            $p['reference']
        );
        return external_api::clean_returnvalue(api::create_highlight_returns(), $result);
    }

    /**
     * Learners see their own highlights and the reference only when the tutor shows it.
     *
     * @return void
     */
    public function test_highlight_visibility(): void {
        $reference = $this->create(0, 20, ['reference' => true]);
        $this->setUser($this->student);
        $own = $this->create(30, 40);
        $this->setUser($this->other);
        $this->create(50, 60);

        $this->setUser($this->student);
        $visible = api::get_highlights($this->cm->id, $this->document->id);
        $this->assertSame([$own['id']], array_column($visible, 'id'), 'Reference hidden by default');

        $this->setUser($this->teacher);
        $this->assertTrue(api::set_reference_visibility($this->cm->id, true));
        $this->setUser($this->student);
        $visible = api::get_highlights($this->cm->id, $this->document->id);
        $this->assertEqualsCanonicalizing([$reference['id'], $own['id']], array_column($visible, 'id'));
        $this->assertSame('#fff176', $own['color']);
    }

    /**
     * Learners cannot add to the reference or change another learner's highlight.
     *
     * @return void
     */
    public function test_learner_permissions(): void {
        $this->setUser($this->student);
        $own = $this->create(10, 20);
        try {
            $this->create(30, 40, ['reference' => true]);
            $this->fail('A learner created a reference highlight');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_string('underlyit:manageannotations', 'underlyit'), $e->getMessage());
        }

        $this->setUser($this->other);
        $this->expectException(\required_capability_exception::class);
        update_highlight::execute($this->cm->id, $own['id'], '#a5d6a7');
    }

    /**
     * Invalid selectors and texts are rejected.
     *
     * @return void
     */
    public function test_validation(): void {
        $this->setUser($this->student);
        $invalid = [
            'beyond the document' => [10, 600, []],
            'empty range' => [10, 10, []],
            'empty text' => [10, 20, ['selectedtext' => '   ']],
            'negative position' => [10, 20, ['positionorder' => -1]],
            'bad colour' => [10, 20, ['color' => 'red']],
        ];
        foreach ($invalid as $name => [$start, $end, $extra]) {
            try {
                $this->create($start, $end, $extra);
                $this->fail("Accepted: {$name}");
            } catch (\invalid_parameter_exception $e) {
                $this->assertNotEmpty($e->getMessage(), $name);
            }
        }
    }

    /**
     * Learners own their highlights: they can recolour and delete them.
     *
     * @return void
     */
    public function test_update_and_delete(): void {
        global $DB;
        $this->setUser($this->student);
        $own = $this->create(10, 20);
        $updated = update_highlight::execute($this->cm->id, $own['id'], '#A5D6A7');
        $this->assertSame('#a5d6a7', $updated['color']);
        $this->assertTrue(api::delete_highlight($this->cm->id, $own['id']));
        $this->assertFalse($DB->record_exists('underlyit_highlight', ['id' => $own['id']]));
    }
}
