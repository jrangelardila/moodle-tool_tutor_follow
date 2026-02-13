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

require_once($CFG->dirroot . '/user/lib.php');

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_course_category;

require_once($CFG->libdir . '/formslib.php');


class form_configuration extends \moodleform
{
    /**
     * @inheritDoc
     */
    protected function definition()
    {
        global $DB, $OUTPUT;
        $mform = $this->_form;

        $mform->addElement(
            'html',
            '<br><h3 class="text-primary">' .
            get_string('selectcategories', 'tool_tutor_follow') .
            '</h3>'
        );

        $mform->addElement(
            'autocomplete',
            'categories',
            get_string('categoriesanalyze', 'tool_tutor_follow'),
            core_course_category::make_categories_list(),
            ['multiple' => true]
        );
        $mform->setDefault('categories', json_decode(get_config('tool_tutor_follow', 'categories')));

        $mform->addElement(
            'html',
            '<br><h3 class="text-primary">' .
            get_string('rolesanalyzed', 'tool_tutor_follow') .
            '</h3>'
        );

        $roles = role_get_names(context_system::instance());
        $roleOptions = [];
        foreach ($roles as $role) {
            if (stripos($role->shortname, 'teacher') !== false) {
                $roleOptions[$role->shortname] = $role->name ?: $role->localname;
            }
        }

        $mform->addElement(
            'autocomplete',
            'roles',
            get_string('roleselect', 'tool_tutor_follow'),
            $roleOptions,
            ['multiple' => true]
        );
        $mform->setDefault('roles', json_decode(get_config('tool_tutor_follow', 'roles')));

        //Reports config
        $mform->addElement(
            'html',
            '<br><h3 class="text-primary">' .
            get_string('reports_configuration', 'tool_tutor_follow') .
            '</h3>'
        );
        // cc_email default
        $attributes = [
            'multiple' => true,
            'ajax' => 'core_user/form_user_selector',
            'placeholder' => get_string('search'),
            'noselectionstring' => get_string('anyvalue', 'tool_tutor_follow'),
        ];
        // Obtener defaults desde config
        $cc_email = json_decode(
            get_config('tool_tutor_follow', 'cc_email_default'),
            true
        );
        $options = [];
        if (!empty($cc_email) && is_array($cc_email)) {
            list($insql, $params) = $DB->get_in_or_equal($cc_email, SQL_PARAMS_NAMED);
            $users = $DB->get_records_select(
                'user',
                "id $insql AND deleted = 0",
                $params,
                '',
                'id, firstname, lastname,email'
            );
            foreach ($users as $user) {
                $options[$user->id] = fullname($user) . ' ' . $user->email;
            }
        }
        $mform->addElement(
            'autocomplete',
            'cc_email',
            get_string('cc_email_select', 'tool_tutor_follow'),
            $options,
            $attributes
        );
        $mform->setType('cc_email', PARAM_INT);
        if (!empty($cc_email) && is_array($cc_email)) {
            $mform->setDefault('cc_email', $cc_email);
        }
        //Execution time week
        $days = [
            1 => get_string('monday', 'calendar'),
            2 => get_string('tuesday', 'calendar'),
            3 => get_string('wednesday', 'calendar'),
            4 => get_string('thursday', 'calendar'),
            5 => get_string('friday', 'calendar'),
            6 => get_string('saturday', 'calendar'),
            0 => get_string('sunday', 'calendar'),
        ];
        $group = [];
        $group[] = $mform->createElement('select', 'execution_day', '', $days);
        $hours = [];
        for ($i = 0; $i <= 23; $i++) {
            $hours[$i] = sprintf('%02d', $i);
        }
        $group[] = $mform->createElement('select', 'execution_hour', '', $hours);
        $minutes = [];
        for ($i = 0; $i <= 59; $i++) {
            $minutes[$i] = sprintf('%02d', $i);
        }
        $group[] = $mform->createElement('select', 'execution_minute', '', $minutes);

        $mform->addGroup($group, 'execution_group', get_string('execution_day', 'tool_tutor_follow'), ' ', false);

        $mform->addElement('html', '<table class="generaltable fullwidth">');
        $mform->addElement('html', '
    <thead>
        <tr>
            <th class="text-center" style="width: 50px;">' . get_string('reportenable', 'tool_tutor_follow') . '</th>
            <th>' . get_string("reportname", "tool_tutor_follow") . '</th>
            <th>' . get_string("reportdescription", "tool_tutor_follow") . '</th>
            <th>' . get_string("download", "tool_tutor_follow") . '</th>
        </tr>
    </thead>
    <tbody>');
        $mform->addElement('html', '<tbody>');

        foreach ($this->get_alllreports() as $report) {
            $mform->addElement('html', '<tr>');

            $elname = "report_enabled[$report->shortname]";
            $checkbox = $mform->createElement('advcheckbox', $elname, '', '', null, array(0, 1));

            $mform->addElement('html', '<td class="text-center" style="vertical-align: middle;">');
            $mform->addGroup([$checkbox], 'group_' . $report->shortname, '', ' ', false);

            $mform->addElement('html', '</td>');

            $mform->addElement('html', "<td style='vertical-align: middle;'><strong>{$report->name}</strong></td>");
            $mform->addElement('html', "<td style='vertical-align: middle;'><small>{$report->description}</small></td>");

            $rawdata = tool_tutor_follow_get_data("report_{$report->shortname}", 'reports');
            if ($rawdata) {
                $downloadurl = new \moodle_url('/admin/tool/tutor_follow/download.php', ['shortname' => $report->shortname]);
                $icon = new \pix_icon('t/download', get_string('download', 'tool_tutor_follow'));
                $downloadbutton = $OUTPUT->action_link($downloadurl, '', null, [
                    'target' => '_blank',
                ], $icon);
                $mform->addElement('html', '<td class="text-center" style="vertical-align: middle;">' . $downloadbutton . '</td>');
            } else {
                $forbiddenicon = \html_writer::tag('i', '', [
                    'class' => 'fa fa-ban text-muted fa-lg',
                    'title' => get_string('nodata', 'tool_tutor_follow'),
                    'data-toggle' => 'tooltip'
                ]);
                $htmlcontent = \html_writer::span($forbiddenicon, 'd-inline-block', [
                    'style' => 'cursor: not-allowed;'
                ]);

                $mform->addElement('html', '<td class="text-center" style="vertical-align: middle;">' . $htmlcontent . '</td>');
            }

            $mform->addElement('html', '</tr>');

            $mform->setType($elname, PARAM_BOOL);
        }
        $mform->addElement('html', '</tbody></table>');

        $mform->addElement('submit', 'submitbutton', get_string('savechanges'));

        $reports = json_decode(get_config('tool_tutor_follow', 'reports_enable'));
        foreach ($reports as $reportname => $enabled) {
            $mform->setDefault("report_enabled[$reportname]", $enabled);
        }
    }

    /**
     * Save the configurations
     *
     * @return void
     */
    public function update_configurations()
    {
        $categories = $this->get_data()->categories;
        $roles = $this->get_data()->roles;

        set_config('categories', json_encode($categories), 'tool_tutor_follow');
        set_config('roles', json_encode($roles), 'tool_tutor_follow');
        set_config('reports_enable', json_encode(
            (object)$this->get_data()->report_enabled
        ), 'tool_tutor_follow');

        $task = \core\task\manager::get_scheduled_task('\\tool_tutor_follow\\task\\execute_reports');
        if ($task) {
            $task->set_day_of_week($this->get_data()->execution_day);
            $task->set_hour($this->get_data()->execution_hour);
            $task->set_minute($this->get_data()->execution_minute);
            $task->set_day('*');
            $task->set_month('*');
            \core\task\manager::configure_scheduled_task($task);
        }
        set_config('cc_email_default', json_encode($this->get_data()->cc_email), 'tool_tutor_follow');
        \core\notification::success(get_string('savechanges', 'tool_tutor_follow'));
    }


    /**
     * Get all report classes
     *
     * @return array<array|string>
     * @throws \coding_exception
     */
    public function get_alllreports()
    {
        $reports = [];
        $directory = __DIR__ . '/../report/';

        if (is_dir($directory)) {
            $files = scandir($directory);
            foreach ($files as $file) {
                if (strpos($file, '.php') !== false && $file[0] !== '.') {
                    $reportname = str_replace('.php', '', $file);

                    if ($reportname === 'report_base') {
                        continue;
                    }

                    $report = new \stdClass();
                    $report->shortname = $reportname;
                    $report->name = get_string('report_' . $reportname, 'tool_tutor_follow');
                    $report->description = get_string('report_' . $reportname . '_desc', 'tool_tutor_follow');

                    $reports[] = $report;
                }
            }
        }
        return $reports;
    }
}
