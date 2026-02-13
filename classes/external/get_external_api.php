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

use context_system;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_api;

use stdClass;

defined('MOODLE_INTERNAL') || die();
class get_external_api extends external_api
{

    /**
     * Struct of params
     *
     * @return external_function_parameters
     */
    public static function get_data_courses_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_TEXT, 'userid', VALUE_OPTIONAL)
            ]
        );
    }

    /**
     * Filter data user
     *
     * @param $id
     * @return false|string
     * @throws \dml_exception
     */
    public static function get_data_courses($id)
    {

        $params = self::validate_parameters(
            self::get_data_courses_parameters(),
            ['id' => $id]
        );

        $context = context_system::instance();

        self::validate_context($context);

        require_capability('tool/tutor_follow:view', $context);

        $data = json_decode(tool_tutor_follow_get_data('json_user_data', 'data_user'), true);

        if (empty($data['users'])) {
            return json_encode([]);
        }

        $obj = new stdClass();
        foreach ($data['users'] as $user) {
            if ($user['id'] == $params['id']) {
                $obj = $user;
                break;
            }
        }

        return json_encode($obj);
    }


    /**
     * Data returns
     *
     * @return external_value
     */
    public static function get_data_courses_returns(): external_value
    {
        return new external_value(PARAM_RAW, 'Data user');
    }

}