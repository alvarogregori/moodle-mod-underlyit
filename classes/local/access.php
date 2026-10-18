<?php
namespace mod_studymark\local;

defined('MOODLE_INTERNAL') || die();

final class access {
    public static function module(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('studymark', $cmid, 0, false, MUST_EXIST);
        $activity = $DB->get_record('studymark', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/studymark:view', $context);
        return [$cm, $activity, $context];
    }

    public static function document(int $cmid, int $documentid): array {
        global $DB;
        [$cm, $activity, $context] = self::module($cmid);
        $document = $DB->get_record('studymark_document', [
            'id' => $documentid, 'studymarkid' => $activity->id,
        ], '*', MUST_EXIST);
        return [$cm, $activity, $context, $document];
    }

    public static function active_group(\stdClass $cm, int $requestedgroupid): int {
        global $DB, $USER;
        if (groups_get_activity_groupmode($cm) == NOGROUPS) {
            return 0;
        }
        $groupid = $requestedgroupid ?: groups_get_activity_group($cm, true);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        if (!$groupid || !groups_group_visible($groupid, $course, $cm, $USER->id)) {
            throw new \moodle_exception('notingroup');
        }
        return $groupid;
    }

    private static function validate_context(\context_module $context): void {
        \core_external\external_api::validate_context($context);
    }
}
