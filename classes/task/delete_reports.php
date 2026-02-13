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

namespace tool_tutor_follow\task;

class delete_reports extends \core\task\scheduled_task
{

    /**
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name()
    {
        return get_string("delete_reports", "tool_tutor_follow");
    }

    /**
     * Delete reports
     *
     * @return void
     * @throws \dml_exception
     */
    public function execute() {
        global $DB;

        $dayslimit = get_config('tool_tutor_follow', 'reports_days_label');
        $dayslimit = ($dayslimit !== false && $dayslimit !== '') ? (int)$dayslimit : 8;

        $timelimit = time() - ($dayslimit * DAYSECS);
        $DB->delete_records_select(
            'tool_tutor_follow_report',
            'lasupdated < :timelimit',
            ['timelimit' => $timelimit]
        );
        mtrace("Cleanup: Removing records where lasupdated is older than " . $dayslimit . " days.");
        mtrace("Threshold date: " . userdate($timelimit));
    }

}