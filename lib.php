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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * StudyMark activity plugin.
 *
 * @package    mod_studymark
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

function studymark_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

function studymark_get_coursemodule_info($coursemodule) {
    global $DB;
    $activity = $DB->get_record('studymark', ['id' => $coursemodule->instance],
        'id, name, completionprogress, completionmatch');
    if (!$activity) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    $info->customdata = ['customcompletionrules' => [
        'completionprogress' => (int)$activity->completionprogress,
        'completionmatch' => (int)$activity->completionmatch,
    ]];
    return $info;
}

function studymark_add_instance($data, $mform = null) {
    global $DB, $USER;
    $data->timecreated = $data->timemodified = time();
    $data->id = $DB->insert_record('studymark', $data);
    studymark_save_documents($data, $USER->id);
    return $data->id;
}

function studymark_update_instance($data, $mform = null) {
    global $DB, $USER;
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('studymark', $data);
    studymark_save_documents($data, $USER->id);
    return true;
}

function studymark_save_documents($data, int $userid): void {
    global $DB;
    if (!isset($data->documents)) {
        return;
    }
    $context = context_module::instance($data->coursemodule);
    file_save_draft_area_files($data->documents, $context->id, 'mod_studymark', 'documents', 0, [
        'subdirs' => false, 'maxfiles' => 1,
        'accepted_types' => ['.pdf', '.epub', '.docx', '.html', '.htm', '.txt'],
    ]);
    $files = get_file_storage()->get_area_files($context->id, 'mod_studymark', 'documents', 0, 'filename', false);
    $file = reset($files) ?: null;
    $existing = $DB->get_record('studymark_document', ['studymarkid' => $data->id]);
    $transaction = $DB->start_delegated_transaction();
    if ($file) {
        $contenthash = $file->get_contenthash();
        $changed = $existing && $existing->contenthash !== $contenthash;
        if ($changed) {
            $DB->delete_records('studymark_comment', ['documentid' => $existing->id]);
            $DB->delete_records('studymark_highlight', ['documentid' => $existing->id]);
        }
        $record = (object) [
            'studymarkid' => $data->id, 'userid' => $existing->userid ?? $userid,
            'title' => pathinfo($file->get_filename(), PATHINFO_FILENAME),
            'filename' => $file->get_filename(), 'mimetype' => $file->get_mimetype(),
            'filesize' => $file->get_filesize(), 'contenthash' => $contenthash,
            'textlength' => $changed ? 0 : (int)($existing->textlength ?? 0), 'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('studymark_document', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('studymark_document', $record);
        }
    } else if ($existing) {
        $DB->delete_records('studymark_comment', ['documentid' => $existing->id]);
        $DB->delete_records('studymark_highlight', ['documentid' => $existing->id]);
        $DB->delete_records('studymark_document', ['id' => $existing->id]);
    }
    $transaction->allow_commit();
}

function studymark_delete_instance($id) {
    global $DB;
    $activity = $DB->get_record('studymark', ['id' => $id]);
    if (!$activity) {
        return false;
    }
    $cm = get_coursemodule_from_instance('studymark', $id);
    if ($cm) {
        get_file_storage()->delete_area_files(context_module::instance($cm->id)->id, 'mod_studymark');
    }
    $documents = $DB->get_records('studymark_document', ['studymarkid' => $id], '', 'id');
    if ($documents) {
        $ids = array_keys($documents);
        $DB->delete_records_list('studymark_comment', 'documentid', $ids);
        $DB->delete_records_list('studymark_highlight', 'documentid', $ids);
        $DB->delete_records_list('studymark_document', 'id', $ids);
    }
    $DB->delete_records('studymark', ['id' => $id]);
    return true;
}

function studymark_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    require_login($course, true, $cm);
    require_capability('mod/studymark:view', $context);
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'documents') {
        return false;
    }
    $itemid = array_shift($args);
    if ((int) $itemid !== 0) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file($context->id, 'mod_studymark', 'documents', 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
