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

namespace tool_tutor_follow\external;

require_once(__DIR__ . "/../../lib.php");

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_api;

use moodle_url;
use stdClass;

class get_external_api extends external_api
{

    public static function get_data_courses_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            [
                'idnumber' => new external_value(PARAM_TEXT, 'ID del elemento', VALUE_OPTIONAL)
            ]
        );
    }

    public static function get_data_courses($idnumber)
    {
        $data = json_decode(tool_tutor_follow_get_data('json_user_data', 'data_user'), true);

        $obj = new stdClass();
        foreach ($data['users'] as $user) {
            if ($user['idnumber'] == $idnumber) {
                $obj = $user;
                break;
            }
        }


        return json_encode($obj);
    }

    public static function get_data_courses_returns(): external_value
    {
        return new external_value(PARAM_RAW, 'Cualquier tipo de datos en formato JSON o estructura de objeto');
    }

}