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

namespace mod_underlyit\local;

/**
 * Access checks shared by the external functions.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /**
     * Loads an activity, validates its context and checks the view capability.
     *
     * @param int $cmid Course module ID.
     * @return array [$cm, $activity, $context]
     */
    public static function module(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('underlyit', $cmid, 0, false, MUST_EXIST);
        $activity = $DB->get_record('underlyit', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        \core_external\external_api::validate_context($context);
        require_capability('mod/underlyit:view', $context);
        return [$cm, $activity, $context];
    }

    /**
     * Loads an activity and one of its documents.
     *
     * @param int $cmid Course module ID.
     * @param int $documentid Document ID.
     * @return array [$cm, $activity, $context, $document]
     */
    public static function document(int $cmid, int $documentid): array {
        global $DB;
        [$cm, $activity, $context] = self::module($cmid);
        $document = $DB->get_record('underlyit_document', [
            'id' => $documentid,
            'underlyitid' => $activity->id,
        ], '*', MUST_EXIST);
        return [$cm, $activity, $context, $document];
    }
}
