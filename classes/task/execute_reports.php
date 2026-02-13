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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version metadata for the repository_pluginname plugin.
 *
 * @package   tool_tutor_follow
 * @copyright 2026, jrangelardila <jrangelardila@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tutor_follow\task;

defined('MOODLE_INTERNAL') || die();

class execute_reports extends \core\task\scheduled_task
{
    /**
     * Execute the task.
     */
    public function execute()
    {
        $categories = json_decode(get_config("tool_tutor_follow", "categories"));
        $roles = json_decode(get_config("tool_tutor_follow", "roles"));
        $reports = json_decode(get_config("tool_tutor_follow", "reports_enable"));
        mtrace("Validate enable reports:");
        foreach ($reports as $reportname => $enabled) {
            if ($enabled) {
                mtrace("" . $reportname);
                $classname = "\\tool_tutor_follow\\report\\" . $reportname;
                $reportInstance = new $classname();
                mtrace("Execute report...");
                $reportInstance->execute($categories, $roles);
            }
        }
    }


    /**
     * return name
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('execute_reports', 'tool_tutor_follow');
    }
}
