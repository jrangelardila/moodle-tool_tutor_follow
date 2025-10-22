<?php

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

$PAGE->navbar->add('Dashboard de Docentes', new moodle_url('/admin/tool/tutor_follow/index.php'));
$PAGE->navbar->add('Detalles');

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
    get_string('globalnews', 'tool_tutor_follow'),
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
