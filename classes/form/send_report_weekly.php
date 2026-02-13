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

namespace tool_tutor_follow\form;

defined('MOODLE_INTERNAL') || die();

class send_report_weekly extends \moodleform
{

    /**
     * Definition
     *
     * @return void
     * @throws \coding_exception
     */
    protected function definition()
    {
        $mform = $this->_form;

        $mform->addElement('header', 'filterheader', get_string('filter', 'tool_tutor_follow'));

        $weeks = $this->get_available_weeks();
        $mform->addElement('autocomplete', 'week', get_string('selectweek', 'tool_tutor_follow'), $weeks, [
            'multiple' => false,
            'noselectionstring' => get_string('selectweek', 'tool_tutor_follow'),
        ]);
        $mform->setType('week', PARAM_INT);
        $mform->setDefault('week', $this->get_current_week_timestamp());
        $mform->addRule('week', get_string('required'), 'required', null, 'client');

        $alerthtml = \html_writer::div(
            \html_writer::tag('i', '', ['class' => 'fa fa-exclamation-triangle mr-2']) .
            get_string('sendreport_warning', 'tool_tutor_follow'),
            'alert alert-warning'
        );
        $mform->addElement('html', $alerthtml);

        $this->add_action_buttons(false, get_string('sendreport', 'tool_tutor_follow'));
    }

    /**
     * Return weeks
     *
     * @return array
     * @throws \coding_exception
     */
    private function get_available_weeks()
    {
        $weeks = [];

        $currentweekstart = $this->get_sunday_of_week(time());

        for ($i = -12; $i <= 4; $i++) {
            $weekstart = strtotime("{$i} weeks", $currentweekstart);
            $weekend = strtotime('+6 days 23:59:59', $weekstart);

            $label = userdate($weekstart, '%a %d/%m/%Y') . ' - ' . userdate($weekend, '%a %d/%m/%Y');

            if ($i === 0) {
                $label .= ' (' . get_string('currentweek', 'tool_tutor_follow') . ')';
            }

            $weeks[$weekstart] = $label;
        }

        return $weeks;
    }

    /**
     * Return sunday
     *
     * @param $timestamp
     * @return false|int
     */
    private function get_sunday_of_week($timestamp)
    {
        $dayofweek = date('w', $timestamp);

        if ($dayofweek == 0) {
            return strtotime('today', $timestamp);
        }

        return strtotime("-{$dayofweek} days", strtotime('today', $timestamp));
    }

    /**
     * Get actualy week
     *
     * @return false|int
     */
    private function get_current_week_timestamp()
    {
        return $this->get_sunday_of_week(time());
    }

    /**
     * Return validation
     *
     * @param $data
     * @param $files
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files)
    {
        $errors = parent::validation($data, $files);

        if (isset($data['week']) && $data['week'] < 0) {
            $errors['week'] = get_string('invalidweek', 'tool_tutor_follow');
        }

        return $errors;
    }

    public function create_adhocs_notified($weekstart, $weekend)
    {
        global $DB;

        $sql = "SELECT * FROM {tool_tutor_follow_report}
            WHERE lasupdated >= :weekstart 
            AND lasupdated <= :weekend
            AND status=0";

        $params = [
            'weekstart' => $weekstart,
            'weekend' => $weekend
        ];

        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $task = new \tool_tutor_follow\adhoc\send_report();
            $task->set_custom_data($record);
            \core\task\manager::queue_adhoc_task($task, true);
        }
        $count = count($records);

        if ($count > 0) {
            \core\notification::success(get_string('adhocs_created_success', 'tool_tutor_follow', $count));
        } else {
            \core\notification::warning(get_string('adhocs_created_empty', 'tool_tutor_follow'));
        }
    }
}