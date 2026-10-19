<?php
namespace mod_studymark\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_studymark\local\access;

final class api extends external_api {
    private static function validate_color(string $color): string {
        if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            throw new \invalid_parameter_exception('Invalid highlight colour');
        }
        return strtolower($color);
    }

    private static function validate_selector(string $selectorjson, int $textlength): void {
        $selector = json_decode($selectorjson, true);
        if (!is_array($selector) || ($selector['type'] ?? '') !== 'text-offset' ||
                !isset($selector['start'], $selector['end']) || !is_int($selector['start']) ||
                !is_int($selector['end']) || $selector['start'] < 0 || $selector['end'] <= $selector['start'] ||
                ($textlength > 0 && $selector['end'] > $textlength)) {
            throw new \invalid_parameter_exception('Invalid text selector');
        }
    }

    private static function document_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Document ID'),
            'title' => new external_value(PARAM_TEXT, 'Title'),
            'filename' => new external_value(PARAM_FILE, 'Filename'),
            'mimetype' => new external_value(PARAM_RAW, 'MIME type'),
            'filesize' => new external_value(PARAM_INT, 'Bytes'),
            'url' => new external_value(PARAM_URL, 'Authenticated File API URL'),
        ]);
    }

    private static function highlight_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Highlight ID'),
            'documentid' => new external_value(PARAM_INT, 'Document ID'),
            'userid' => new external_value(PARAM_INT, 'Author'),
            'userfullname' => new external_value(PARAM_TEXT, 'Author display name'),
            'groupid' => new external_value(PARAM_INT, 'Moodle group ID'),
            'selectedtext' => new external_value(PARAM_RAW, 'Selected text'),
            'selectorjson' => new external_value(PARAM_RAW, 'Serialized anchor', VALUE_OPTIONAL, ''),
            'pagenumber' => new external_value(PARAM_INT, 'Page', VALUE_OPTIONAL, 0),
            'color' => new external_value(PARAM_TEXT, 'Colour'),
            'note' => new external_value(PARAM_RAW, 'Note', VALUE_OPTIONAL, ''),
            'headingcontext' => new external_value(PARAM_RAW, 'Heading', VALUE_OPTIONAL, ''),
            'positionorder' => new external_value(PARAM_FLOAT, 'Position'),
            'shared' => new external_value(PARAM_BOOL, 'Shared'),
            'reference' => new external_value(PARAM_BOOL, 'Reference highlight'),
            'timecreated' => new external_value(PARAM_INT, 'Created'),
            'canedit' => new external_value(PARAM_BOOL, 'Can edit'),
        ]);
    }

    private static function comment_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Comment ID'),
            'documentid' => new external_value(PARAM_INT, 'Document ID'),
            'highlightid' => new external_value(PARAM_INT, 'Highlight ID', VALUE_OPTIONAL, 0),
            'userid' => new external_value(PARAM_INT, 'Author'),
            'userfullname' => new external_value(PARAM_TEXT, 'Author display name'),
            'groupid' => new external_value(PARAM_INT, 'Moodle group ID'),
            'content' => new external_value(PARAM_RAW, 'Comment'),
            'timecreated' => new external_value(PARAM_INT, 'Created'),
            'canedit' => new external_value(PARAM_BOOL, 'Can edit'),
        ]);
    }

    public static function get_documents_parameters(): external_function_parameters {
        return new external_function_parameters(['cmid' => new external_value(PARAM_INT, 'Course module ID')]);
    }
    public static function get_documents(int $cmid): array {
        global $DB;
        [$cm, $activity, $context] = access::module($cmid);
        $result = [];
        foreach ($DB->get_records('studymark_document', ['studymarkid' => $activity->id], 'title, id') as $document) {
            $result[] = [
                'id' => $document->id, 'title' => $document->title, 'filename' => $document->filename,
                'mimetype' => $document->mimetype, 'filesize' => $document->filesize,
                'url' => \moodle_url::make_pluginfile_url($context->id, 'mod_studymark', 'documents', 0, '/', $document->filename, false)->out(false),
            ];
        }
        return $result;
    }
    public static function get_documents_returns(): external_multiple_structure { return new external_multiple_structure(self::document_structure()); }

    public static function delete_document_parameters(): external_function_parameters {
        return new external_function_parameters(['cmid' => new external_value(PARAM_INT, 'CM ID'), 'documentid' => new external_value(PARAM_INT, 'Document ID')]);
    }
    public static function delete_document(int $cmid, int $documentid): bool {
        global $DB;
        [$cm, $activity, $context, $document] = access::document($cmid, $documentid);
        require_capability('mod/studymark:managefiles', $context);
        $file = get_file_storage()->get_file($context->id, 'mod_studymark', 'documents', 0, '/', $document->filename);
        if ($file) { $file->delete(); }
        $DB->delete_records('studymark_comment', ['documentid' => $documentid]);
        $DB->delete_records('studymark_highlight', ['documentid' => $documentid]);
        $DB->delete_records('studymark_document', ['id' => $documentid]);
        return true;
    }
    public static function delete_document_returns(): external_value { return new external_value(PARAM_BOOL, 'Deleted'); }

    public static function get_highlights_parameters(): external_function_parameters {
        return new external_function_parameters(['cmid' => new external_value(PARAM_INT, 'CM ID'), 'documentid' => new external_value(PARAM_INT, 'Document ID'), 'groupid' => new external_value(PARAM_INT, 'Group ID', VALUE_DEFAULT, 0)]);
    }
    public static function get_highlights(int $cmid, int $documentid, int $groupid = 0): array {
        global $DB, $USER;
        [$cm, , $context, $document] = access::document($cmid, $documentid);
        $groupid = access::active_group($cm, $groupid);
        $params = ['documentid' => $documentid, 'userid' => $USER->id, 'groupid' => $groupid];
        $sql = "SELECT h.*, u.firstname, u.lastname
                  FROM {studymark_highlight} h JOIN {user} u ON u.id = h.userid
                 WHERE h.documentid = :documentid
                   AND (h.userid = :userid OR h.reference = 1 OR (h.shared = 1 AND h.groupid = :groupid))
              ORDER BY h.positionorder, h.id";
        return array_values(array_map(fn($h) => self::export_highlight($h, $context), $DB->get_records_sql($sql, $params)));
    }
    public static function get_highlights_returns(): external_multiple_structure { return new external_multiple_structure(self::highlight_structure()); }

    public static function create_highlight_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'CM ID'), 'documentid' => new external_value(PARAM_INT, 'Document ID'),
            'groupid' => new external_value(PARAM_INT, 'Group ID', VALUE_DEFAULT, 0),
            'selectedtext' => new external_value(PARAM_RAW, 'Selected text'), 'selectorjson' => new external_value(PARAM_RAW, 'Serialized anchor', VALUE_DEFAULT, ''),
            'pagenumber' => new external_value(PARAM_INT, 'Page', VALUE_DEFAULT, 0), 'color' => new external_value(PARAM_TEXT, 'Colour', VALUE_DEFAULT, '#ffff00'),
            'note' => new external_value(PARAM_RAW, 'Note', VALUE_DEFAULT, ''), 'headingcontext' => new external_value(PARAM_RAW, 'Heading', VALUE_DEFAULT, ''),
            'positionorder' => new external_value(PARAM_FLOAT, 'Position', VALUE_DEFAULT, 0), 'shared' => new external_value(PARAM_BOOL, 'Shared', VALUE_DEFAULT, false),
            'reference' => new external_value(PARAM_BOOL, 'Reference highlight', VALUE_DEFAULT, false),
        ]);
    }
    public static function create_highlight($cmid, $documentid, $groupid, $selectedtext, $selectorjson, $pagenumber, $color, $note, $headingcontext, $positionorder, $shared, $reference = false): array {
        global $DB, $USER;
        [$cm, , $context, $document] = access::document($cmid, $documentid);
        require_capability('mod/studymark:addhighlight', $context);
        if ($shared) { require_capability('mod/studymark:sharehighlight', $context); }
        $groupid = $shared ? access::active_group($cm, $groupid) : 0;
        if ($reference) { require_capability('mod/studymark:manageannotations', $context); }
        self::validate_selector($selectorjson, (int)$document->textlength);
        $color = self::validate_color($color);
        $record = (object) compact('documentid', 'groupid', 'selectedtext', 'selectorjson', 'pagenumber', 'color', 'note', 'headingcontext', 'positionorder', 'shared', 'reference');
        $record->userid = $USER->id; $record->timecreated = $record->timemodified = time();
        $record->id = $DB->insert_record('studymark_highlight', $record);
        $record->firstname = $USER->firstname; $record->lastname = $USER->lastname;
        \mod_studymark\event\highlight_created::create(['objectid' => $record->id, 'context' => $context, 'relateduserid' => $USER->id])->trigger();
        \mod_studymark\local\completion::refresh($cm, $context, $USER->id, $reference);
        return self::export_highlight($record, $context);
    }
    public static function create_highlight_returns(): external_single_structure { return self::highlight_structure(); }

    public static function delete_highlight_parameters(): external_function_parameters {
        return new external_function_parameters(['cmid' => new external_value(PARAM_INT, 'CM ID'), 'highlightid' => new external_value(PARAM_INT, 'Highlight ID')]);
    }
    public static function delete_highlight(int $cmid, int $highlightid): bool {
        global $DB, $USER;
        [$cm, $activity, $context] = access::module($cmid);
        $h = $DB->get_record_sql("SELECT h.* FROM {studymark_highlight} h JOIN {studymark_document} d ON d.id=h.documentid WHERE h.id=? AND d.studymarkid=?", [$highlightid, $activity->id], MUST_EXIST);
        if ($h->userid != $USER->id && !has_capability('mod/studymark:manageannotations', $context)) { throw new \required_capability_exception($context, 'mod/studymark:manageannotations', 'nopermissions', ''); }
        $DB->delete_records('studymark_comment', ['highlightid' => $highlightid]);
        $DB->delete_records('studymark_highlight', ['id' => $highlightid]);
        \mod_studymark\local\completion::refresh($cm, $context, (int)$h->userid, !empty($h->reference));
        return true;
    }
    public static function delete_highlight_returns(): external_value { return new external_value(PARAM_BOOL, 'Deleted'); }

    public static function set_document_length_parameters(): external_function_parameters {
        return new external_function_parameters(['cmid' => new external_value(PARAM_INT, 'CM ID'),
            'documentid' => new external_value(PARAM_INT, 'Document ID'),
            'textlength' => new external_value(PARAM_INT, 'Normalized character count')]);
    }
    public static function set_document_length(int $cmid, int $documentid, int $textlength): bool {
        global $DB;
        [$cm, , $context] = access::document($cmid, $documentid);
        require_capability('mod/studymark:manageannotations', $context);
        if ($textlength < 1) { throw new \invalid_parameter_exception('Invalid document length'); }
        $current = (int)$DB->get_field('studymark_document', 'textlength', ['id' => $documentid], MUST_EXIST);
        if ($current !== $textlength) {
            $DB->set_field('studymark_document', 'textlength', $textlength, ['id' => $documentid]);
            \mod_studymark\local\completion::refresh($cm, $context, 0, true);
        }
        return true;
    }
    public static function set_document_length_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Saved');
    }

    public static function get_comments_parameters(): external_function_parameters {
        return new external_function_parameters(['cmid' => new external_value(PARAM_INT, 'CM ID'), 'documentid' => new external_value(PARAM_INT, 'Document ID'), 'groupid' => new external_value(PARAM_INT, 'Group ID', VALUE_DEFAULT, 0)]);
    }
    public static function get_comments(int $cmid, int $documentid, int $groupid = 0): array {
        global $DB, $USER;
        [$cm, , $context] = access::document($cmid, $documentid);
        $groupid = access::active_group($cm, $groupid);
        $sql = "SELECT c.*, u.firstname, u.lastname FROM {studymark_comment} c JOIN {user} u ON u.id=c.userid WHERE c.documentid=:documentid AND c.groupid=:groupid ORDER BY c.timecreated";
        $records = $DB->get_records_sql($sql, ['documentid' => $documentid, 'groupid' => $groupid]);
        return array_values(array_map(fn($c) => self::export_comment($c, $context), $records));
    }
    public static function get_comments_returns(): external_multiple_structure { return new external_multiple_structure(self::comment_structure()); }

    public static function create_comment_parameters(): external_function_parameters {
        return new external_function_parameters(['cmid' => new external_value(PARAM_INT, 'CM ID'), 'documentid' => new external_value(PARAM_INT, 'Document ID'), 'highlightid' => new external_value(PARAM_INT, 'Highlight ID', VALUE_DEFAULT, 0), 'groupid' => new external_value(PARAM_INT, 'Group ID', VALUE_DEFAULT, 0), 'content' => new external_value(PARAM_RAW, 'Comment')]);
    }
    public static function create_comment(int $cmid, int $documentid, int $highlightid, int $groupid, string $content): array {
        global $DB, $USER;
        [$cm, , $context] = access::document($cmid, $documentid);
        require_capability('mod/studymark:addcomment', $context);
        $groupid = access::active_group($cm, $groupid);
        if ($highlightid) {
            $highlight = $DB->get_record('studymark_highlight', ['id' => $highlightid, 'documentid' => $documentid], '*', MUST_EXIST);
            $visible = (int)$highlight->userid === (int)$USER->id || !empty($highlight->reference) ||
                (!empty($highlight->shared) && (int)$highlight->groupid === $groupid) ||
                has_capability('mod/studymark:manageannotations', $context);
            if (!$visible) { throw new \invalid_parameter_exception('Invalid highlight'); }
        }
        $record = (object) ['documentid' => $documentid, 'highlightid' => $highlightid ?: null, 'userid' => $USER->id, 'groupid' => $groupid, 'content' => trim($content), 'timecreated' => time(), 'timemodified' => time()];
        if ($record->content === '') { throw new \invalid_parameter_exception('Comment is empty'); }
        $record->id = $DB->insert_record('studymark_comment', $record);
        $record->firstname = $USER->firstname; $record->lastname = $USER->lastname;
        \mod_studymark\event\comment_created::create(['objectid' => $record->id, 'context' => $context, 'relateduserid' => $USER->id])->trigger();
        return self::export_comment($record, $context);
    }
    public static function create_comment_returns(): external_single_structure { return self::comment_structure(); }

    public static function delete_comment_parameters(): external_function_parameters {
        return new external_function_parameters(['cmid' => new external_value(PARAM_INT, 'CM ID'), 'commentid' => new external_value(PARAM_INT, 'Comment ID')]);
    }
    public static function delete_comment(int $cmid, int $commentid): bool {
        global $DB, $USER;
        [$cm, $activity, $context] = access::module($cmid);
        $c = $DB->get_record_sql("SELECT c.* FROM {studymark_comment} c JOIN {studymark_document} d ON d.id=c.documentid WHERE c.id=? AND d.studymarkid=?", [$commentid, $activity->id], MUST_EXIST);
        if ($c->userid != $USER->id && !has_capability('mod/studymark:manageannotations', $context)) { throw new \required_capability_exception($context, 'mod/studymark:manageannotations', 'nopermissions', ''); }
        $DB->delete_records('studymark_comment', ['id' => $commentid]);
        return true;
    }
    public static function delete_comment_returns(): external_value { return new external_value(PARAM_BOOL, 'Deleted'); }

    private static function export_highlight($h, $context): array {
        global $USER;
        return ['id'=>(int)$h->id,'documentid'=>(int)$h->documentid,'userid'=>(int)$h->userid,'userfullname'=>fullname($h),'groupid'=>(int)$h->groupid,'selectedtext'=>$h->selectedtext,'selectorjson'=>$h->selectorjson ?? '','pagenumber'=>(int)($h->pagenumber ?? 0),'color'=>$h->color,'note'=>$h->note ?? '','headingcontext'=>$h->headingcontext ?? '','positionorder'=>(float)$h->positionorder,'shared'=>(bool)$h->shared,'reference'=>(bool)($h->reference ?? false),'timecreated'=>(int)$h->timecreated,'canedit'=>$h->userid == $USER->id || has_capability('mod/studymark:manageannotations',$context)];
    }
    private static function export_comment($c, $context): array {
        global $USER;
        return ['id'=>(int)$c->id,'documentid'=>(int)$c->documentid,'highlightid'=>(int)($c->highlightid ?? 0),'userid'=>(int)$c->userid,'userfullname'=>fullname($c),'groupid'=>(int)$c->groupid,'content'=>$c->content,'timecreated'=>(int)$c->timecreated,'canedit'=>$c->userid == $USER->id || has_capability('mod/studymark:manageannotations',$context)];
    }
}
