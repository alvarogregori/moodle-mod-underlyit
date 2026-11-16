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
 * External functions of the Underly.it activity.
 *
 * @package    mod_underlyit
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_underlyit_get_documents' => [
        'classname' => 'mod_underlyit\external\api',
        'methodname' => 'get_documents',
        'description' => 'List activity documents',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/underlyit:view',
    ],
    'mod_underlyit_get_highlights' => [
        'classname' => 'mod_underlyit\external\api',
        'methodname' => 'get_highlights',
        'description' => 'List visible highlights',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/underlyit:view',
    ],
    'mod_underlyit_create_highlight' => [
        'classname' => 'mod_underlyit\external\api',
        'methodname' => 'create_highlight',
        'description' => 'Create a highlight',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/underlyit:addhighlight',
    ],
    'mod_underlyit_update_highlight' => [
        'classname' => 'mod_underlyit\external\update_highlight',
        'methodname' => 'execute',
        'description' => 'Update a highlight',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/underlyit:addhighlight',
    ],
    'mod_underlyit_delete_highlight' => [
        'classname' => 'mod_underlyit\external\api',
        'methodname' => 'delete_highlight',
        'description' => 'Delete a highlight',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/underlyit:addhighlight',
    ],
    'mod_underlyit_set_document_length' => [
        'classname' => 'mod_underlyit\external\api',
        'methodname' => 'set_document_length',
        'description' => 'Set trusted normalized document length',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/underlyit:manageannotations',
    ],
    'mod_underlyit_set_reference_visibility' => [
        'classname' => 'mod_underlyit\external\api',
        'methodname' => 'set_reference_visibility',
        'description' => 'Show or hide the tutor reference for learners',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/underlyit:manageannotations',
    ],
    'mod_underlyit_get_analytics' => [
        'classname' => 'mod_underlyit\external\analytics',
        'methodname' => 'execute',
        'description' => 'Get student highlighting analytics',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/underlyit:manageannotations',
    ],
];
