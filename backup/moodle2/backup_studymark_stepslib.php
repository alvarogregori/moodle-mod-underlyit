<?php
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
