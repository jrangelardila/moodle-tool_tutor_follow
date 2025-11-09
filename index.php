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

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$PAGE->set_url(new moodle_url('/admin/tool/tutor_follow/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'tool_tutor_follow'));
$PAGE->set_heading(get_string('pluginname', 'tool_tutor_follow'));

require_capability('tool/tutor_follow:view', context_system::instance());


$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('tool_tutor_follow/div4', 'init');
$PAGE->requires->js_call_amd('tool_tutor_follow/reports', 'add_report');

echo $OUTPUT->header();

echo html_writer::tag('img', "", [
    "src" => new moodle_url('/admin/tool/tutor_follow/img.png'),
    'class' => 'w-100'
]);
echo html_writer::tag('hr', '');
echo html_writer::tag('h2', get_string('hi', 'tool_tutor_follow') . " " . fullname($USER), [
    'class' => 'text-left text-primary',
]);
echo html_writer::tag('hr', '');

$option = optional_param('i', 1, PARAM_TEXT);

tool_tutor_follow_print_bar($OUTPUT, $option, [
    get_string('grades', 'tool_tutor_follow'),
    get_string('globalnews', 'tool_tutor_follow'),
    get_string('reports', 'tool_tutor_follow'),
    get_string('studentsdistribution', 'tool_tutor_follow'),
    get_string('settings', 'tool_tutor_follow'),
]);

echo "<br><br><hr>";
echo html_writer::start_div("data-body");
echo html_writer::end_div();
echo "<hr>";

switch ($option) {
    case 1:
        tool_tutor_follow_option1();
        break;
    case 2:
        tool_tutor_follow_option2();
        break;
    case 3:
        tool_tutor_follow_option3();
        break;
    case 4:
        tool_tutor_follow_option4();
        break;
    case 5:
        $form = new \tool_tutor_follow\form\form_configuration(
            action: new moodle_url('/admin/tool/tutor_follow/index.php?i=4')
        );
        if ($form->get_data()) {
            $form->update_configurations();
        }
        $form->display();
        break;
}

echo $OUTPUT->footer();
