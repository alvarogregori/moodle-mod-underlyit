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
$functions = [
    'mod_studymark_get_documents' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'get_documents','description'=>'List activity documents','type'=>'read','ajax'=>true,'capabilities'=>'mod/studymark:view'],
    'mod_studymark_delete_document' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'delete_document','description'=>'Delete a document','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:managefiles'],
    'mod_studymark_get_highlights' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'get_highlights','description'=>'List visible highlights','type'=>'read','ajax'=>true,'capabilities'=>'mod/studymark:view'],
    'mod_studymark_create_highlight' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'create_highlight','description'=>'Create a highlight','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:addhighlight'],
    'mod_studymark_update_highlight' => ['classname'=>'mod_studymark\\external\\update_highlight','methodname'=>'execute','description'=>'Update a highlight','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:addhighlight'],
    'mod_studymark_delete_highlight' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'delete_highlight','description'=>'Delete a highlight','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:addhighlight'],
    'mod_studymark_set_document_length' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'set_document_length','description'=>'Set trusted normalized document length','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:manageannotations'],
    'mod_studymark_set_reference_visibility' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'set_reference_visibility','description'=>'Show or hide the tutor reference for learners','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:manageannotations'],
    'mod_studymark_get_comments' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'get_comments','description'=>'List comments','type'=>'read','ajax'=>true,'capabilities'=>'mod/studymark:view'],
    'mod_studymark_create_comment' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'create_comment','description'=>'Create a comment','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:addcomment'],
    'mod_studymark_delete_comment' => ['classname'=>'mod_studymark\\external\\api','methodname'=>'delete_comment','description'=>'Delete a comment','type'=>'write','ajax'=>true,'capabilities'=>'mod/studymark:addcomment'],
    'mod_studymark_get_analytics' => ['classname'=>'mod_studymark\\external\\analytics','methodname'=>'execute','description'=>'Get student highlighting analytics','type'=>'read','ajax'=>true,'capabilities'=>'mod/studymark:manageannotations'],
];
