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
 * Underly.it activity plugin.
 *
 * @package    mod_studymark
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_studymark_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026101802) {
        $table = new xmldb_table('studymark_document');
        $oldindex = new xmldb_index('activity_filename', XMLDB_INDEX_UNIQUE, ['studymarkid', 'filename']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $newindex = new xmldb_index('activity_unique', XMLDB_INDEX_UNIQUE, ['studymarkid']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        upgrade_mod_savepoint(true, 2026101802, 'studymark');
    }
    if ($oldversion < 2026101900) {
        $table = new xmldb_table('studymark');
        $progress = new xmldb_field('completionprogress', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0', 'introformat');
        if (!$dbman->field_exists($table, $progress)) { $dbman->add_field($table, $progress); }
        $match = new xmldb_field('completionmatch', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0', 'completionprogress');
        if (!$dbman->field_exists($table, $match)) { $dbman->add_field($table, $match); }
        $documenttable = new xmldb_table('studymark_document');
        $textlength = new xmldb_field('textlength', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'filesize');
        if (!$dbman->field_exists($documenttable, $textlength)) { $dbman->add_field($documenttable, $textlength); }
        upgrade_mod_savepoint(true, 2026101900, 'studymark');
    }
    if ($oldversion < 2026101903) {
        $table = new xmldb_table('studymark_document');
        $field = new xmldb_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '', 'filesize');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $highlighttable = new xmldb_table('studymark_highlight');
        $reference = new xmldb_field('reference', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'shared');
        if (!$dbman->field_exists($highlighttable, $reference)) {
            $dbman->add_field($highlighttable, $reference);
        }
        $sql = "SELECT d.id, f.contenthash
                  FROM {studymark_document} d
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = d.studymarkid
                  JOIN {context} ctx ON ctx.contextlevel = :contextlevel AND ctx.instanceid = cm.id
                  JOIN {files} f ON f.contextid = ctx.id AND f.component = :component
                       AND f.filearea = :filearea AND f.itemid = 0 AND f.filename = d.filename";
        $params = ['modname' => 'studymark', 'contextlevel' => CONTEXT_MODULE,
            'component' => 'mod_studymark', 'filearea' => 'documents'];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $DB->set_field('studymark_document', 'contenthash', $record->contenthash, ['id' => $record->id]);
        }
        upgrade_mod_savepoint(true, 2026101903, 'studymark');
    }
    if ($oldversion < 2026102106) {
        $table = new xmldb_table('studymark');
        $field = new xmldb_field('showreference', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'completionmatch');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026102106, 'studymark');
    }
    if ($oldversion < 2026102108) {
        upgrade_mod_savepoint(true, 2026102108, 'studymark');
    }
    if ($oldversion < 2026102200) {
        upgrade_mod_savepoint(true, 2026102200, 'studymark');
    }
    if ($oldversion < 2026102201) {
        upgrade_mod_savepoint(true, 2026102201, 'studymark');
    }
    if ($oldversion < 2026111300) {
        upgrade_mod_savepoint(true, 2026111300, 'studymark');
    }
    return true;
}
