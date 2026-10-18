<?php
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
        'subdirs' => false,
        'maxfiles' => 1,
        'accepted_types' => ['.pdf', '.epub', '.docx', '.html', '.htm', '.txt'],
    ]);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_studymark', 'documents', 0, 'filename', false);
    $seen = [];
    foreach ($files as $file) {
        $seen[] = $file->get_filename();
        $existing = $DB->get_record('studymark_document', ['studymarkid' => $data->id, 'filename' => $file->get_filename()]);
        $record = (object) [
            'studymarkid' => $data->id, 'userid' => $existing->userid ?? $userid,
            'title' => pathinfo($file->get_filename(), PATHINFO_FILENAME),
            'filename' => $file->get_filename(), 'mimetype' => $file->get_mimetype(),
            'filesize' => $file->get_filesize(), 'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('studymark_document', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('studymark_document', $record);
        }
    }
    if ($seen) {
        [$sql, $params] = $DB->get_in_or_equal($seen, SQL_PARAMS_NAMED, 'fn', false);
        $params['sid'] = $data->id;
        $DB->delete_records_select('studymark_document', "studymarkid = :sid AND filename $sql", $params);
    } else {
        $DB->delete_records('studymark_document', ['studymarkid' => $data->id]);
    }
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
