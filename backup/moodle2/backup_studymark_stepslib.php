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
class backup_studymark_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $activity = new backup_nested_element('studymark', ['id'], ['name','intro','introformat','completionprogress','completionmatch','showreference','timecreated','timemodified']);
        $documents = new backup_nested_element('documents');
        $document = new backup_nested_element('document', ['id'], ['userid','title','filename','mimetype','filesize','contenthash','textlength','timecreated','timemodified']);
        $highlights = new backup_nested_element('highlights');
        $highlight = new backup_nested_element('highlight', ['id'], ['userid','groupid','selectedtext','selectorjson','pagenumber','color','note','headingcontext','positionorder','shared','reference','timecreated','timemodified']);
        $comments = new backup_nested_element('comments');
        $comment = new backup_nested_element('comment', ['id'], ['highlightid','userid','groupid','content','timecreated','timemodified']);
        $activity->add_child($documents); $documents->add_child($document); $document->add_child($highlights); $highlights->add_child($highlight); $document->add_child($comments); $comments->add_child($comment);
        $activity->set_source_table('studymark', ['id'=>backup::VAR_ACTIVITYID]);
        $document->set_source_table('studymark_document', ['studymarkid'=>backup::VAR_PARENTID]);
        if ($userinfo) { $highlight->set_source_table('studymark_highlight', ['documentid'=>backup::VAR_PARENTID]); $comment->set_source_table('studymark_comment', ['documentid'=>backup::VAR_PARENTID]); }
        $document->annotate_ids('user','userid'); $highlight->annotate_ids('user','userid'); $highlight->annotate_ids('group','groupid'); $comment->annotate_ids('user','userid'); $comment->annotate_ids('group','groupid');
        $activity->annotate_files('mod_studymark','documents',null);
        return $this->prepare_activity_structure($activity);
    }
}
