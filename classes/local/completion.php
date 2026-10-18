<?php
namespace mod_studymark\local;

defined('MOODLE_INTERNAL') || die();

final class completion {
    public static function refresh(\stdClass $cm, \context_module $context, int $userid, bool $referencechanged = false): void {
        global $DB;
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $info = new \completion_info($course);
        if (!$info->is_enabled($cm)) { return; }
        if ($referencechanged) {
            foreach (get_enrolled_users($context, 'mod/studymark:addhighlight', 0, 'u.id') as $user) {
                if (!has_capability('mod/studymark:manageannotations', $context, $user->id)) {
                    $info->update_state($cm, COMPLETION_UNKNOWN, $user->id);
                }
            }
        } else {
            $info->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }
}
