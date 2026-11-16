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
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_underlyit\local\access;
use mod_underlyit\local\completion;

/**
 * External functions for documents and highlights.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api extends external_api {
    /** Maximum length, in characters, of the highlighted text. */
    private const MAX_SELECTED_TEXT = 20000;

    /** Maximum length, in characters, of the heading context. */
    private const MAX_HEADING_CONTEXT = 1000;

    /** Maximum length, in bytes, of the JSON selector. */
    private const MAX_SELECTOR = 255;

    /**
     * Validates the free-text and numeric values of a new highlight sent by the browser.
     *
     * @param array $params Validated parameters of create_highlight.
     * @return array The parameters with the highlighted text trimmed.
     */
    private static function validate_highlight_values(array $params): array {
        $params['selectedtext'] = trim($params['selectedtext']);
        $length = \core_text::strlen($params['selectedtext']);
        if ($length === 0 || $length > self::MAX_SELECTED_TEXT) {
            throw new \invalid_parameter_exception('Invalid highlighted text length');
        }
        if (\core_text::strlen($params['headingcontext']) > self::MAX_HEADING_CONTEXT) {
            throw new \invalid_parameter_exception('Heading context is too long');
        }
        if (strlen($params['selectorjson']) > self::MAX_SELECTOR) {
            throw new \invalid_parameter_exception('Invalid text selector');
        }
        if ($params['pagenumber'] < 0 || !is_finite($params['positionorder']) || $params['positionorder'] < 0) {
            throw new \invalid_parameter_exception('Invalid highlight position');
        }
        return $params;
    }

    /**
     * Validates a highlight colour.
     *
     * @param string $color Colour in #rrggbb form.
     * @return string The colour in lower case.
     */
    private static function validate_color(string $color): string {
        if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            throw new \invalid_parameter_exception('Invalid highlight colour');
        }
        return strtolower($color);
    }

    /**
     * Validates a text-offset selector.
     *
     * @param string $selectorjson JSON-encoded selector.
     * @param int $textlength Registered document length, or 0 if unknown.
     * @return void
     */
    private static function validate_selector(string $selectorjson, int $textlength): void {
        $selector = json_decode($selectorjson, true);
        $valid = is_array($selector)
            && ($selector['type'] ?? '') === 'text-offset'
            && isset($selector['start'], $selector['end'])
            && is_int($selector['start'])
            && is_int($selector['end'])
            && $selector['start'] >= 0
            && $selector['end'] > $selector['start']
            && ($textlength === 0 || $selector['end'] <= $textlength);
        if (!$valid) {
            throw new \invalid_parameter_exception('Invalid text selector');
        }
    }

    /**
     * Returns the SQL select list of the user name fields.
     *
     * @return string
     */
    private static function user_name_sql(): string {
        return \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    }

    /**
     * Copies the current user's name fields onto a record so that fullname() works.
     *
     * @param \stdClass $record Record to complete.
     * @return void
     */
    private static function add_current_user_names(\stdClass $record): void {
        global $USER;
        foreach (\core_user\fields::get_name_fields() as $field) {
            $record->$field = $USER->$field ?? '';
        }
    }

    /**
     * Describes a document.
     *
     * @return external_single_structure
     */
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

    /**
     * Describes a highlight.
     *
     * @return external_single_structure
     */
    private static function highlight_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Highlight ID'),
            'documentid' => new external_value(PARAM_INT, 'Document ID'),
            'userid' => new external_value(PARAM_INT, 'Author'),
            'userfullname' => new external_value(PARAM_TEXT, 'Author display name'),
            'selectedtext' => new external_value(PARAM_RAW, 'Selected text'),
            'selectorjson' => new external_value(PARAM_RAW, 'Serialized anchor', VALUE_OPTIONAL, ''),
            'pagenumber' => new external_value(PARAM_INT, 'Page', VALUE_OPTIONAL, 0),
            'color' => new external_value(PARAM_TEXT, 'Colour'),
            'headingcontext' => new external_value(PARAM_RAW, 'Heading', VALUE_OPTIONAL, ''),
            'positionorder' => new external_value(PARAM_FLOAT, 'Position'),
            'reference' => new external_value(PARAM_BOOL, 'Reference highlight'),
            'timecreated' => new external_value(PARAM_INT, 'Created'),
            'canedit' => new external_value(PARAM_BOOL, 'Can edit'),
        ]);
    }

    /**
     * Parameters of get_documents.
     *
     * @return external_function_parameters
     */
    public static function get_documents_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Lists the documents of an activity.
     *
     * @param int $cmid Course module ID.
     * @return array
     */
    public static function get_documents(int $cmid): array {
        global $DB;
        ['cmid' => $cmid] = self::validate_parameters(self::get_documents_parameters(), ['cmid' => $cmid]);
        [, $activity, $context] = access::module($cmid);
        $result = [];
        foreach ($DB->get_records('underlyit_document', ['underlyitid' => $activity->id], 'title, id') as $document) {
            $url = \moodle_url::make_pluginfile_url(
                $context->id,
                'mod_underlyit',
                'documents',
                0,
                '/',
                $document->filename,
                false
            );
            $result[] = [
                'id' => $document->id,
                'title' => $document->title,
                'filename' => $document->filename,
                'mimetype' => $document->mimetype,
                'filesize' => $document->filesize,
                'url' => $url->out(false),
            ];
        }
        return $result;
    }

    /**
     * Return value of get_documents.
     *
     * @return external_multiple_structure
     */
    public static function get_documents_returns(): external_multiple_structure {
        return new external_multiple_structure(self::document_structure());
    }

    /**
     * Parameters of get_highlights.
     *
     * @return external_function_parameters
     */
    public static function get_highlights_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'documentid' => new external_value(PARAM_INT, 'Document ID'),
        ]);
    }

    /**
     * Lists the highlights visible to the current user.
     *
     * These are the user's own highlights and, when it is visible, the tutor reference.
     *
     * @param int $cmid Course module ID.
     * @param int $documentid Document ID.
     * @return array
     */
    public static function get_highlights(int $cmid, int $documentid): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::get_highlights_parameters(), [
            'cmid' => $cmid,
            'documentid' => $documentid,
        ]);
        [, $activity, $context, $document] = access::document($params['cmid'], $params['documentid']);
        $canmanage = has_capability('mod/underlyit:manageannotations', $context);
        $showreference = $canmanage || !empty($activity->showreference);
        $sql = "SELECT h.*, " . self::user_name_sql() . "
                  FROM {underlyit_highlight} h
                  JOIN {user} u ON u.id = h.userid
                 WHERE h.documentid = :documentid
                   AND (h.userid = :userid OR (h.reference = 1 AND :showreference = 1))
              ORDER BY h.positionorder, h.id";
        $records = $DB->get_records_sql($sql, [
            'documentid' => $document->id,
            'userid' => $USER->id,
            'showreference' => (int)$showreference,
        ]);
        return array_values(array_map(fn($highlight) => self::export_highlight($highlight, $context), $records));
    }

    /**
     * Return value of get_highlights.
     *
     * @return external_multiple_structure
     */
    public static function get_highlights_returns(): external_multiple_structure {
        return new external_multiple_structure(self::highlight_structure());
    }

    /**
     * Parameters of create_highlight.
     *
     * @return external_function_parameters
     */
    public static function create_highlight_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'documentid' => new external_value(PARAM_INT, 'Document ID'),
            'selectedtext' => new external_value(PARAM_RAW, 'Selected text'),
            'selectorjson' => new external_value(PARAM_RAW, 'Serialized anchor', VALUE_DEFAULT, ''),
            'pagenumber' => new external_value(PARAM_INT, 'Page', VALUE_DEFAULT, 0),
            'color' => new external_value(PARAM_TEXT, 'Colour', VALUE_DEFAULT, '#ffff00'),
            'headingcontext' => new external_value(PARAM_RAW, 'Heading', VALUE_DEFAULT, ''),
            'positionorder' => new external_value(PARAM_FLOAT, 'Position', VALUE_DEFAULT, 0),
            'reference' => new external_value(PARAM_BOOL, 'Reference highlight', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Creates a highlight.
     *
     * @param int $cmid Course module ID.
     * @param int $documentid Document ID.
     * @param string $selectedtext Highlighted text.
     * @param string $selectorjson JSON-encoded text-offset selector.
     * @param int $pagenumber Page number, if known.
     * @param string $color Colour in #rrggbb form.
     * @param string $headingcontext Heading of the section.
     * @param float $positionorder Sort position.
     * @param bool $reference Whether the highlight belongs to the tutor reference.
     * @return array
     */
    public static function create_highlight(
        int $cmid,
        int $documentid,
        string $selectedtext,
        string $selectorjson,
        int $pagenumber,
        string $color,
        string $headingcontext,
        float $positionorder,
        bool $reference = false
    ): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::create_highlight_parameters(), [
            'cmid' => $cmid,
            'documentid' => $documentid,
            'selectedtext' => $selectedtext,
            'selectorjson' => $selectorjson,
            'pagenumber' => $pagenumber,
            'color' => $color,
            'headingcontext' => $headingcontext,
            'positionorder' => $positionorder,
            'reference' => $reference,
        ]);
        [$cm, , $context, $document] = access::document($params['cmid'], $params['documentid']);
        require_capability('mod/underlyit:addhighlight', $context);
        if ($params['reference']) {
            require_capability('mod/underlyit:manageannotations', $context);
        }
        $params = self::validate_highlight_values($params);
        self::validate_selector($params['selectorjson'], (int)$document->textlength);

        $record = (object) [
            'documentid' => $document->id,
            'userid' => $USER->id,
            'selectedtext' => $params['selectedtext'],
            'selectorjson' => $params['selectorjson'],
            'pagenumber' => $params['pagenumber'],
            'color' => self::validate_color($params['color']),
            'headingcontext' => $params['headingcontext'],
            'positionorder' => $params['positionorder'],
            'reference' => (int)$params['reference'],
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('underlyit_highlight', $record);
        self::add_current_user_names($record);

        \mod_underlyit\event\highlight_created::create([
            'objectid' => $record->id,
            'context' => $context,
        ])->trigger();
        completion::refresh($cm, $context, $USER->id, $params['reference']);
        return self::export_highlight($record, $context);
    }

    /**
     * Return value of create_highlight.
     *
     * @return external_single_structure
     */
    public static function create_highlight_returns(): external_single_structure {
        return self::highlight_structure();
    }

    /**
     * Parameters of delete_highlight.
     *
     * @return external_function_parameters
     */
    public static function delete_highlight_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'highlightid' => new external_value(PARAM_INT, 'Highlight ID'),
        ]);
    }

    /**
     * Deletes a highlight.
     *
     * @param int $cmid Course module ID.
     * @param int $highlightid Highlight ID.
     * @return bool
     */
    public static function delete_highlight(int $cmid, int $highlightid): bool {
        global $DB, $USER;
        $params = self::validate_parameters(self::delete_highlight_parameters(), [
            'cmid' => $cmid,
            'highlightid' => $highlightid,
        ]);
        [$cm, $activity, $context] = access::module($params['cmid']);
        $highlight = $DB->get_record_sql(
            "SELECT h.*
               FROM {underlyit_highlight} h
               JOIN {underlyit_document} d ON d.id = h.documentid
              WHERE h.id = :id AND d.underlyitid = :underlyitid",
            ['id' => $params['highlightid'], 'underlyitid' => $activity->id],
            MUST_EXIST
        );
        if ($highlight->userid != $USER->id) {
            require_capability('mod/underlyit:manageannotations', $context);
        }
        $DB->delete_records('underlyit_highlight', ['id' => $highlight->id]);
        completion::refresh($cm, $context, (int)$highlight->userid, !empty($highlight->reference));
        return true;
    }

    /**
     * Return value of delete_highlight.
     *
     * @return external_value
     */
    public static function delete_highlight_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Deleted');
    }

    /**
     * Parameters of set_document_length.
     *
     * @return external_function_parameters
     */
    public static function set_document_length_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'documentid' => new external_value(PARAM_INT, 'Document ID'),
            'textlength' => new external_value(PARAM_INT, 'Normalized character count'),
        ]);
    }

    /**
     * Registers the normalised character count of a document.
     *
     * @param int $cmid Course module ID.
     * @param int $documentid Document ID.
     * @param int $textlength Normalised character count.
     * @return bool
     */
    public static function set_document_length(int $cmid, int $documentid, int $textlength): bool {
        global $DB;
        $params = self::validate_parameters(self::set_document_length_parameters(), [
            'cmid' => $cmid,
            'documentid' => $documentid,
            'textlength' => $textlength,
        ]);
        [$cm, , $context, $document] = access::document($params['cmid'], $params['documentid']);
        require_capability('mod/underlyit:manageannotations', $context);
        if ($params['textlength'] < 1) {
            throw new \invalid_parameter_exception('Invalid document length');
        }
        if ((int)$document->textlength !== $params['textlength']) {
            $DB->set_field('underlyit_document', 'textlength', $params['textlength'], ['id' => $document->id]);
            completion::refresh($cm, $context, 0, true);
        }
        return true;
    }

    /**
     * Return value of set_document_length.
     *
     * @return external_value
     */
    public static function set_document_length_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Saved');
    }

    /**
     * Parameters of set_reference_visibility.
     *
     * @return external_function_parameters
     */
    public static function set_reference_visibility_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'visible' => new external_value(PARAM_BOOL, 'Whether learners may see the tutor reference'),
        ]);
    }

    /**
     * Shows or hides the tutor reference for learners.
     *
     * @param int $cmid Course module ID.
     * @param bool $visible Whether learners may see the reference.
     * @return bool The saved visibility.
     */
    public static function set_reference_visibility(int $cmid, bool $visible): bool {
        global $DB;
        $params = self::validate_parameters(self::set_reference_visibility_parameters(), [
            'cmid' => $cmid,
            'visible' => $visible,
        ]);
        [, $activity, $context] = access::module($params['cmid']);
        require_capability('mod/underlyit:manageannotations', $context);
        $DB->set_field('underlyit', 'showreference', (int)$params['visible'], ['id' => $activity->id]);
        return $params['visible'];
    }

    /**
     * Return value of set_reference_visibility.
     *
     * @return external_value
     */
    public static function set_reference_visibility_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Saved reference visibility');
    }

    /**
     * Exports a highlight record.
     *
     * @param \stdClass $highlight Highlight record with the author's name fields.
     * @param \context_module $context Module context.
     * @return array
     */
    private static function export_highlight(\stdClass $highlight, \context_module $context): array {
        global $USER;
        return [
            'id' => (int)$highlight->id,
            'documentid' => (int)$highlight->documentid,
            'userid' => (int)$highlight->userid,
            'userfullname' => fullname($highlight),
            'selectedtext' => $highlight->selectedtext,
            'selectorjson' => $highlight->selectorjson ?? '',
            'pagenumber' => (int)($highlight->pagenumber ?? 0),
            'color' => $highlight->color,
            'headingcontext' => $highlight->headingcontext ?? '',
            'positionorder' => (float)$highlight->positionorder,
            'reference' => (bool)($highlight->reference ?? false),
            'timecreated' => (int)$highlight->timecreated,
            'canedit' => $highlight->userid == $USER->id || has_capability('mod/underlyit:manageannotations', $context),
        ];
    }
}
