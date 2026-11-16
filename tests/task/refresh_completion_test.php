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

namespace mod_underlyit\task;

use mod_underlyit\local\completion;

/**
 * Tests the background recalculation of completion after reference changes.
 *
 * @package    mod_underlyit
 * @category   test
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_underlyit\task\refresh_completion
 * @covers     \mod_underlyit\local\completion
 */
final class refresh_completion_test extends \advanced_testcase {
    /**
     * Creates a course with completion, a tutor, a learner and an activity that requires a 50% match.
     *
     * @return array [$cm, $context, $document, $teacher, $student, $generator]
     */
    private function setup_activity(): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('underlyit', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionmatch' => 50,
        ]);
        $cm = get_coursemodule_from_instance('underlyit', $activity->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_underlyit');
        $this->setUser($teacher);
        $document = $generator->create_document($activity, 100);
        return [$cm, $context, $document, $teacher, $student, $generator];
    }

    /**
     * Completion state of a user.
     *
     * @param \stdClass $cm Course module.
     * @param int $userid User ID.
     * @return int
     */
    private function state(\stdClass $cm, int $userid): int {
        $info = new \completion_info(get_course($cm->course));
        return (int)$info->get_data($cm, false, $userid)->completionstate;
    }

    /**
     * A reference change queues a single task, and running it completes the learner.
     *
     * @return void
     */
    public function test_reference_change_is_recalculated_by_one_task(): void {
        [$cm, $context, $document, $teacher, $student, $generator] = $this->setup_activity();
        $generator->create_highlight($document, $student->id, 0, 50);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($cm, $student->id));

        $generator->create_highlight($document, $teacher->id, 0, 50, true);
        completion::refresh($cm, $context, $teacher->id, true);
        completion::refresh($cm, $context, $teacher->id, true);

        $tasks = \core\task\manager::get_adhoc_tasks(refresh_completion::class);
        $this->assertCount(1, $tasks);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($cm, $student->id), 'Recalculated inside the request');

        ob_start();
        $this->runAdhocTasks(refresh_completion::class);
        $output = ob_get_clean();
        $this->assertStringContainsString('Recalculated the completion of 1 learners', $output);
        $this->assertSame(COMPLETION_COMPLETE, $this->state($cm, $student->id));
        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(refresh_completion::class));
    }

    /**
     * A learner's own change is recalculated at once without a task.
     *
     * @return void
     */
    public function test_learner_change_is_recalculated_at_once(): void {
        [$cm, $context, $document, $teacher, $student, $generator] = $this->setup_activity();
        $generator->create_highlight($document, $teacher->id, 0, 50, true);
        $generator->create_highlight($document, $student->id, 0, 50);

        completion::refresh($cm, $context, $student->id);

        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(refresh_completion::class));
        $this->assertSame(COMPLETION_COMPLETE, $this->state($cm, $student->id));
    }

    /**
     * The task does nothing when the activity has been deleted meanwhile.
     *
     * @return void
     */
    public function test_task_tolerates_deleted_activity(): void {
        [$cm, $context] = $this->setup_activity();
        completion::refresh($cm, $context, 0, true);
        course_delete_module($cm->id);

        ob_start();
        $this->runAdhocTasks(refresh_completion::class);
        $output = ob_get_clean();
        $this->assertStringContainsString('no longer exists', $output);
    }
}
