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
 * Library of interface functions and constants for the Underly.it activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** File types accepted as the activity document. */
const UNDERLYIT_ACCEPTED_TYPES = ['.pdf', '.epub', '.docx', '.html', '.htm', '.txt'];

/**
 * Reports the Moodle features supported by this activity.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True or a value if the feature is supported, null if unknown.
 */
function underlyit_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Returns the information cached in the course modinfo for an activity.
 *
 * @param stdClass $coursemodule Course module record.
 * @return cached_cm_info|null
 */
function underlyit_get_coursemodule_info($coursemodule) {
    global $DB;
    $activity = $DB->get_record(
        'underlyit',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, completionprogress, completionmatch'
    );
    if (!$activity) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('underlyit', $activity, $coursemodule->id, false);
    }
    $info->customdata = ['customcompletionrules' => [
        'completionprogress' => (int)$activity->completionprogress,
        'completionmatch' => (int)$activity->completionmatch,
    ]];
    return $info;
}

/**
 * Creates a new activity instance.
 *
 * @param stdClass $data Form data.
 * @param mod_underlyit_mod_form|null $mform The form.
 * @return int The new instance ID.
 */
function underlyit_add_instance($data, $mform = null) {
    global $DB, $USER;
    $data->timecreated = $data->timemodified = time();
    $data->id = $DB->insert_record('underlyit', $data);
    underlyit_save_documents($data, $USER->id);
    return $data->id;
}

/**
 * Updates an existing activity instance.
 *
 * @param stdClass $data Form data.
 * @param mod_underlyit_mod_form|null $mform The form.
 * @return bool
 */
function underlyit_update_instance($data, $mform = null) {
    global $DB, $USER;
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('underlyit', $data);
    underlyit_save_documents($data, $USER->id);
    return true;
}

/**
 * Stores the uploaded document and keeps its metadata record in sync.
 *
 * Replacing the document with different content removes the highlights
 * anchored to the previous one, because their offsets no longer apply.
 *
 * @param stdClass $data Form data including the instance ID and draft item ID.
 * @param int $userid Uploader user ID.
 * @return void
 */
function underlyit_save_documents($data, int $userid): void {
    global $DB;
    if (!isset($data->documents)) {
        return;
    }
    $context = context_module::instance($data->coursemodule);
    file_save_draft_area_files($data->documents, $context->id, 'mod_underlyit', 'documents', 0, [
        'subdirs' => false,
        'maxfiles' => 1,
        'accepted_types' => UNDERLYIT_ACCEPTED_TYPES,
    ]);
    $files = get_file_storage()->get_area_files($context->id, 'mod_underlyit', 'documents', 0, 'filename', false);
    $file = reset($files) ?: null;
    $existing = $DB->get_record('underlyit_document', ['underlyitid' => $data->id]);
    $transaction = $DB->start_delegated_transaction();
    if ($file) {
        $contenthash = $file->get_contenthash();
        $changed = $existing && $existing->contenthash !== $contenthash;
        if ($changed) {
            $DB->delete_records('underlyit_highlight', ['documentid' => $existing->id]);
        }
        $record = (object) [
            'underlyitid' => $data->id,
            'userid' => $existing->userid ?? $userid,
            'title' => pathinfo($file->get_filename(), PATHINFO_FILENAME),
            'filename' => $file->get_filename(),
            'mimetype' => $file->get_mimetype(),
            'filesize' => $file->get_filesize(),
            'contenthash' => $contenthash,
            'textlength' => $changed ? 0 : (int)($existing->textlength ?? 0),
            'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('underlyit_document', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('underlyit_document', $record);
        }
    } else if ($existing) {
        $DB->delete_records('underlyit_highlight', ['documentid' => $existing->id]);
        $DB->delete_records('underlyit_document', ['id' => $existing->id]);
    }
    $transaction->allow_commit();
}

/**
 * Deletes an activity instance and all its data.
 *
 * @param int $id Instance ID.
 * @return bool
 */
function underlyit_delete_instance($id) {
    global $DB;
    $activity = $DB->get_record('underlyit', ['id' => $id]);
    if (!$activity) {
        return false;
    }
    $cm = get_coursemodule_from_instance('underlyit', $id);
    if ($cm) {
        get_file_storage()->delete_area_files(context_module::instance($cm->id)->id, 'mod_underlyit');
    }
    $documents = $DB->get_records('underlyit_document', ['underlyitid' => $id], '', 'id');
    if ($documents) {
        $ids = array_keys($documents);
        $DB->delete_records_list('underlyit_highlight', 'documentid', $ids);
        $DB->delete_records_list('underlyit_document', 'id', $ids);
    }
    $DB->delete_records('underlyit', ['id' => $id]);
    return true;
}

/**
 * Serves the activity document.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module record.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args Remaining file path arguments.
 * @param bool $forcedownload Whether to force a download.
 * @param array $options Additional send_stored_file() options.
 * @return bool False if the file was not found; otherwise the file is sent and the script ends.
 */
function underlyit_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'documents') {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/underlyit:view', $context);
    $itemid = array_shift($args);
    if ((int) $itemid !== 0) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file($context->id, 'mod_underlyit', 'documents', 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
