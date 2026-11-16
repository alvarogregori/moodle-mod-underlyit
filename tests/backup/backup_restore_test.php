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

namespace mod_underlyit\backup;

/**
 * Tests backup and restore of the Underly.it activity.
 *
 * @package    mod_underlyit
 * @category   test
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_underlyit_activity_task
 * @covers     \backup_underlyit_activity_structure_step
 * @covers     \restore_underlyit_activity_task
 * @covers     \restore_underlyit_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Creates a course with an activity that has a description image, a document and highlights.
     *
     * @return array [$course, $activity, $teacher, $student]
     */
    private function create_course_with_activity(): array {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('underlyit', [
            'course' => $course->id,
            'name' => 'Reading',
            'completionmatch' => 40,
            'showreference' => 1,
        ]);
        $cm = get_coursemodule_from_instance('underlyit', $activity->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $intro = '<p><img src="@@PLUGINFILE@@/picture.png" alt="Picture"></p>' .
            '<p><a href="' . $CFG->wwwroot . '/mod/underlyit/view.php?id=' . $cm->id . '">Back to this reading</a></p>';
        $this->set_intro($activity->id, $intro);
        $fs = get_file_storage();
        $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_underlyit', 'filearea' => 'intro',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'picture.png'], 'image bytes');
        $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_underlyit', 'filearea' => 'documents',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'document.txt'], 'Document text');

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_underlyit');
        $document = $generator->create_document($activity, 13);
        $generator->create_highlight($document, $teacher->id, 0, 8, true);
        $generator->create_highlight($document, $student->id, 9, 13);
        return [$course, $activity, $teacher, $student];
    }

    /**
     * Stores the description of an activity.
     *
     * @param int $id Activity ID.
     * @param string $intro Description HTML.
     * @return void
     */
    private function set_intro(int $id, string $intro): void {
        global $DB;
        $DB->update_record('underlyit', (object)['id' => $id, 'intro' => $intro, 'introformat' => FORMAT_HTML]);
    }

    /**
     * Backs up a course and restores it into a new course.
     *
     * @param \stdClass $course Course to back up.
     * @param bool $users Whether to include user data.
     * @return int ID of the new course.
     */
    private function backup_and_restore(\stdClass $course, bool $users): int {
        global $CFG, $USER;
        // Keep the backup directory so that it can be restored without unzipping.
        $CFG->keeptempdirectoriesonbackup = true;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($users);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'RESTORED', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value($users);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * With user data, the activity, its files and every highlight are restored.
     *
     * @return void
     */
    public function test_backup_restore_with_user_data(): void {
        global $DB;
        [$course, $activity, $teacher, $student] = $this->create_course_with_activity();

        $newcourseid = $this->backup_and_restore($course, true);

        $restored = $DB->get_record('underlyit', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertNotEquals($activity->id, $restored->id);
        $this->assertSame('Reading', $restored->name);
        $this->assertEquals(40, $restored->completionmatch);
        $this->assertEquals(1, $restored->showreference);

        $newcm = get_coursemodule_from_instance('underlyit', $restored->id, $newcourseid, false, MUST_EXIST);
        $this->assertStringContainsString(
            '/mod/underlyit/view.php?id=' . $newcm->id . '"',
            $restored->intro,
            'Link to the activity points to the restored course module'
        );

        $context = \context_module::instance($newcm->id);
        $fs = get_file_storage();
        $intro = $fs->get_area_files($context->id, 'mod_underlyit', 'intro', 0, 'filename', false);
        $this->assertSame(['picture.png'], array_values(array_map(fn($f) => $f->get_filename(), $intro)));
        $documents = $fs->get_area_files($context->id, 'mod_underlyit', 'documents', 0, 'filename', false);
        $this->assertSame('Document text', reset($documents)->get_content());

        $document = $DB->get_record('underlyit_document', ['underlyitid' => $restored->id], '*', MUST_EXIST);
        $this->assertEquals(13, $document->textlength);
        $highlights = $DB->get_records('underlyit_highlight', ['documentid' => $document->id], 'positionorder');
        $this->assertCount(2, $highlights);
        [$reference, $personal] = array_values($highlights);
        $this->assertEquals($teacher->id, $reference->userid);
        $this->assertEquals(1, $reference->reference);
        $this->assertEquals($student->id, $personal->userid);
        $this->assertEquals(0, $personal->reference);
        $this->assertSame(json_encode(['type' => 'text-offset', 'start' => 9, 'end' => 13]), $personal->selectorjson);
    }

    /**
     * Without user data, the activity and its document are restored but no highlights.
     *
     * @return void
     */
    public function test_backup_restore_without_user_data(): void {
        global $DB;
        [$course] = $this->create_course_with_activity();

        $newcourseid = $this->backup_and_restore($course, false);

        $restored = $DB->get_record('underlyit', ['course' => $newcourseid], '*', MUST_EXIST);
        $document = $DB->get_record('underlyit_document', ['underlyitid' => $restored->id], '*', MUST_EXIST);
        $this->assertSame(0, $DB->count_records('underlyit_highlight', ['documentid' => $document->id]));
    }
}
