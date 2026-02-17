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

global $DB;

use tool_tutor_follow\task\data_user_tutor;

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');


require_login();

$PAGE->set_url(new moodle_url('/admin/tool/tutor_follow/details.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'tool_tutor_follow'));
$PAGE->set_heading(get_string('pluginname', 'tool_tutor_follow'));
$PAGE->set_pagelayout('base');
$PAGE->requires->jquery();

require_capability('tool/tutor_follow:view', context_system::instance());

$PAGE->navbar->add(get_string('pluginname', 'tool_tutor_follow'), new moodle_url('/admin/tool/tutor_follow/index.php'));
$PAGE->navbar->add(get_string('details', 'tool_tutor_follow'));

echo $OUTPUT->header();


echo html_writer::tag('img', "", [
    "src" => new moodle_url('/admin/tool/tutor_follow/img.png'),
    'class' => 'w-100'
]);
echo html_writer::tag('hr', '');
echo html_writer::tag('h2', get_string('hi', 'tool_tutor_follow') . fullname($USER), [
    'class' => 'text-left text-primary',
]);

tool_tutor_follow_print_bar($OUTPUT, 1, [
    get_string('grades', 'tool_tutor_follow'),
    get_string('send_reports', 'tool_tutor_follow'),
    get_string('reports', 'tool_tutor_follow'),
    get_string('studentsdistribution', 'tool_tutor_follow'),
    get_string('settings', 'tool_tutor_follow'),
]);


$courseid = optional_param("courseid", "", PARAM_INT);

if (empty($courseid)) {
    throw new moodle_exception('missingcourseid', 'tool_tutor_follow');
}


$form = new \tool_tutor_follow\form\filter_user_data(
    action: new moodle_url('/admin/tool/tutor_follow/details.php', [
        'courseid' => $courseid,
    ]),
    method: 'get',
    elements: ['endtime']
);
$form->display();

tool_tutor_follow_details_table_course($courseid, $form->get_data()->endtime);

echo $OUTPUT->footer();
