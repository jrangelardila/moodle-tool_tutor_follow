<?php
// This file is part of Moodle - https://moodle.org/
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
 * Plugin strings are defined here.
 *
 * @package     tool_tutor_follow
 * @category    string
 * @copyright   2024 Jhon Rangel <jrangelardila@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = [
    'tool_tutor_follow_get_data_course_for_user' => [
        'classname' => 'tool_tutor_follow\external\get_external_api',
        'methodname' => 'get_data_courses',
        'description' => get_string('get_data_course_for_user_desc', 'tool_tutor_follow'),
        'type' => 'write',
        'ajax' => true,
        'classpath' => 'admin/tool/tutor_follow/classes/external/get_external_api.php',
        'capabilities' => 'tool/tutor_follow:view'
    ]
];

