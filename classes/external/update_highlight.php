<?php
namespace mod_studymark\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_studymark\local\access;

final class update_highlight extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'highlightid' => new external_value(PARAM_INT, 'Highlight ID'),
            'color' => new external_value(PARAM_TEXT, 'Colour'),
            'note' => new external_value(PARAM_RAW, 'Note'),
            'shared' => new external_value(PARAM_BOOL, 'Shared'),
            'groupid' => new external_value(PARAM_INT, 'Moodle group ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $cmid, int $highlightid, string $color, string $note, bool $shared, int $groupid = 0): array {
        global $DB, $USER;
        [$cm, $activity, $context] = access::module($cmid);
        $record = $DB->get_record_sql(
            'SELECT h.* FROM {studymark_highlight} h JOIN {studymark_document} d ON d.id=h.documentid WHERE h.id=? AND d.studymarkid=?',
            [$highlightid, $activity->id], MUST_EXIST
        );
        if ($record->userid != $USER->id && !has_capability('mod/studymark:manageannotations', $context)) {
            throw new \required_capability_exception($context, 'mod/studymark:manageannotations', 'nopermissions', '');
        }
        if ($shared) {
            require_capability('mod/studymark:sharehighlight', $context);
            $groupid = access::active_group($cm, $groupid);
        } else {
            $groupid = 0;
        }
        $record->color = $color;
        $record->note = $note;
        $record->shared = (int) $shared;
        $record->groupid = $groupid;
        $record->timemodified = time();
        $DB->update_record('studymark_highlight', $record);
        return ['id' => $record->id, 'color' => $record->color, 'note' => $record->note, 'shared' => (bool) $record->shared, 'groupid' => $record->groupid];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Highlight ID'),
            'color' => new external_value(PARAM_TEXT, 'Colour'),
            'note' => new external_value(PARAM_RAW, 'Note'),
            'shared' => new external_value(PARAM_BOOL, 'Shared'),
            'groupid' => new external_value(PARAM_INT, 'Moodle group ID'),
        ]);
    }
}
