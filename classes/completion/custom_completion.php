<?php
namespace mod_studymark\completion;

defined('MOODLE_INTERNAL') || die();

use core_completion\activity_custom_completion;
use mod_studymark\local\metrics;

final class custom_completion extends activity_custom_completion {
    public function get_state(string $rule): int {
        global $DB;
        $activity = $DB->get_record('studymark', ['id' => $this->cm->instance], '*', MUST_EXIST);
        if (!in_array($rule, self::get_defined_custom_rules(), true)) {
            throw new \coding_exception('Invalid completion rule: ' . $rule);
        }
        $document = $DB->get_record('studymark_document', ['studymarkid' => $activity->id]);
        if (!$document || empty($document->textlength)) {
            return COMPLETION_INCOMPLETE;
        }
        $context = \context_module::instance($this->cm->id);
        $values = metrics::user_metrics($document->id, $this->userid, $document->textlength, $context);
        if ($rule === 'completionprogress') {
            return $values['progress'] >= (int)$activity->completionprogress ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }
        return $values['hasreference'] && $values['match'] >= (int)$activity->completionmatch
            ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    public static function get_defined_custom_rules(): array {
        return ['completionprogress', 'completionmatch'];
    }

    public function get_custom_rule_descriptions(): array {
        global $DB;
        $activity = $DB->get_record('studymark', ['id' => $this->cm->instance],
            'completionprogress, completionmatch', MUST_EXIST);
        return [
            'completionprogress' => get_string('completionprogressdesc', 'studymark', $activity->completionprogress),
            'completionmatch' => get_string('completionmatchdesc', 'studymark', $activity->completionmatch),
        ];
    }

    public function get_sort_order(): array {
        return ['completionprogress', 'completionmatch'];
    }
}
