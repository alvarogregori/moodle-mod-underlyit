<?php
defined('MOODLE_INTERNAL') || die();
$functions = [
    'mod_studymark_get_documents' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'get_documents','description'=>'List activity documents','type'=>'read','ajax'=>true,'capabilities'=>'mod/studymark:view'],
    'mod_studymark_delete_document' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'delete_document','description'=>'Delete a document','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:managefiles'],
    'mod_studymark_get_highlights' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'get_highlights','description'=>'List visible highlights','type'=>'read','ajax'=>true,'capabilities'=>'mod/studymark:view'],
    'mod_studymark_create_highlight' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'create_highlight','description'=>'Create a highlight','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:addhighlight'],
    'mod_studymark_update_highlight' => ['classname'=>'mod_studymark\\external\\update_highlight','methodname'=>'execute','description'=>'Update a highlight','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:addhighlight'],
    'mod_studymark_delete_highlight' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'delete_highlight','description'=>'Delete a highlight','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:addhighlight'],
    'mod_studymark_get_comments' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'get_comments','description'=>'List comments','type'=>'read','ajax'=>true,'capabilities'=>'mod/studymark:view'],
    'mod_studymark_create_comment' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'create_comment','description'=>'Create a comment','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:addcomment'],
    'mod_studymark_delete_comment' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'delete_comment','description'=>'Delete a comment','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:addcomment'],
    'mod_studymark_get_analytics' => ['classname'=>'mod_studymark\\external\\analytics','methodname'=>'execute','description'=>'Get student highlighting analytics','type'=>'read','ajax'=>true,'capabilities'=>'mod/studymark:manageannotations'],
];
