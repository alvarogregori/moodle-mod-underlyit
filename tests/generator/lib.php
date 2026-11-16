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

/**
 * Data generator for the Underly.it activity.
 *
 * @package    mod_underlyit
 * @category   test
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_underlyit_generator extends testing_module_generator {
    /**
     * Creates an activity instance.
     *
     * @param array|stdClass|null $record Instance fields.
     * @param array|null $options Course module options.
     * @return stdClass Activity record with cmid.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        $record->completionprogress = $record->completionprogress ?? 0;
        $record->completionmatch = $record->completionmatch ?? 0;
        $record->showreference = $record->showreference ?? 0;
        return parent::create_instance($record, (array)$options);
    }

    /**
     * Creates the document record of an activity, without a stored file.
     *
     * @param stdClass $activity Activity record.
     * @param int $textlength Normalised document length.
     * @return stdClass Document record.
     */
    public function create_document(stdClass $activity, int $textlength = 1000): stdClass {
        global $DB, $USER;
        $record = (object) [
            'underlyitid' => $activity->id,
            'userid' => $USER->id,
            'title' => 'Document',
            'filename' => 'document.txt',
            'mimetype' => 'text/plain',
            'filesize' => $textlength,
            'contenthash' => sha1('document' . $activity->id),
            'textlength' => $textlength,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('underlyit_document', $record);
        return $record;
    }

    /**
     * Creates a highlight.
     *
     * @param stdClass $document Document record.
     * @param int $userid Author.
     * @param int $start Start offset.
     * @param int $end End offset.
     * @param bool $reference Whether it belongs to the tutor reference.
     * @return stdClass Highlight record.
     */
    public function create_highlight(stdClass $document, int $userid, int $start, int $end, bool $reference = false): stdClass {
        global $DB;
        $record = (object) [
            'documentid' => $document->id,
            'userid' => $userid,
            'selectedtext' => 'text',
            'selectorjson' => json_encode(['type' => 'text-offset', 'start' => $start, 'end' => $end]),
            'pagenumber' => 0,
            'color' => '#fff176',
            'headingcontext' => '',
            'positionorder' => $start,
            'reference' => (int)$reference,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('underlyit_highlight', $record);
        return $record;
    }
}
