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

use core\chart_bar;
use core\chart_series;
use tool_tutor_follow\local\dedication_time;
use tool_tutor_follow\local\report_teacher;
use tool_tutor_follow\task\data_user_tutor;

/**
 * Return localfile
 *
 * @param $course
 * @param $cm
 * @param $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @param array $options
 * @return false|void
 * @throws coding_exception
 */
function tool_tutor_follow_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = [])
{
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }

    $itemid = array_shift($args);

    $filename = array_pop($args);
    $filepath = '/' . (empty($args) ? '' : implode('/', $args) . '/');

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'tool_tutor_follow', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}


/**
 * Return navbar
 *
 * @param $OUTPUT
 * @param $id
 * @param $options
 * @return void
 * @throws coding_exception
 */
function tool_tutor_follow_print_bar($OUTPUT, $id, $options)
{
    $templatecontext = [
        'title' => get_string('actionmenu', 'tool_tutor_follow'),
    ];


    for ($i = 1; $i <= sizeof($options); $i++) {
        $templatecontext["button" . $i . "url"] = new moodle_url("/admin/tool/tutor_follow/index.php?i=$i");
        $templatecontext["button" . $i . "text"] = $options[$i - 1];

        if ($i == $id) {
            $templatecontext[$i . "classes"] = "btn btn-secondary border font-weight-bold";
        } else {
            $templatecontext[$i . "classes"] = "btn btn-primary font-weight-bold";
        }
    }
    echo $OUTPUT->render_from_template('tool_tutor_follow/menu', $templatecontext);
}

/**
 * Return second div
 *
 * @return void
 * @throws dml_exception
 * @throws moodle_exception
 */
function tool_tutor_follow_option1()
{
    $data = json_decode(tool_tutor_follow_get_data('json_user_data', 'data_user'));

    if (!is_object($data)) {
        $data = new stdClass();
    }
    $data->lastejecution = tool_tutor_follow_get_lastime_execution_task('\tool_tutor_follow\task\data_user_tutor');

    echo '<div class="dtl-hero">'
        . '<p class="dtl-hero-meta">' . get_string('lastupdate', 'tool_tutor_follow') . ': <strong>' . $data->lastejecution . '</strong></p>'
        . '<span class="dtl-hero-title"><i class="fa fa-chalkboard-teacher fa-sm"></i>' . get_string('grades', 'tool_tutor_follow') . '</span>'
        . '</div>';

    echo "<div>";
    tool_tutor_follow_print_data($data);
    echo "</div>";
}

/**
 * Pintar la data en mustache
 * @param $data
 * @return void
 * @throws dml_exception
 * @throws moodle_exception
 */
function tool_tutor_follow_print_data($data)
{
    global $OUTPUT;

    $form = new \tool_tutor_follow\form\filter_user_data(
        elements: ['user', 'category']
    );

    $filters_cache = cache::make('tool_tutor_follow', 'form_cache');
    if ($filters = $form->get_data()) {
        $filters_cache->set("filter_users", $filters->user);
        $filters_cache->set("filter_category", $filters->category);
        foreach ($data->users as $value) {
            foreach ($value->cursos as $curso) {
                $userFilterSize = sizeof($filters->user);
                $categoryFilterSize = sizeof($filters->category);
                $userFilterActive = $userFilterSize > 0;
                $categoryFilterActive = $categoryFilterSize > 0;
                if (
                    ($userFilterActive && !in_array($value->id, $filters->user)) ||
                    ($categoryFilterActive && !in_array($curso->category, $filters->category))
                ) {
                    continue;
                }
                $curso->idnumber = $value->idnumber;
                $value->profile_url = (new moodle_url("/user/profile.php", ['id' => $value->id]))->out();
                $value->name = "{$value->firstname} {$value->lastname}";

                $value->datails_user = (new moodle_url("/admin/tool/tutor_follow/details.php", ['userid' => $value->id]))->out();

                $curso->course_url = (new moodle_url("/course/view.php", ['id' => $curso->id]))->out();
                $curso->course_name = "{$curso->shortname} - {$curso->fullname}";

                $category = core_course_category::get($curso->category);
                $curso->category_url = (new moodle_url("/course/index.php", ['categoryid' => $curso->category]))->out();
                $curso->category_name = $category->name;

                $curso->course_details = (new moodle_url("/admin/tool/tutor_follow/details.php", ['courseid' => $curso->id]))->out();

                $curso->dedication_time = tool_tutor_follow_get_time_connection(null, $curso->id, $value->id);
            }
        }
        echo $OUTPUT->render_from_template('tool_tutor_follow/data_user_info', $data);
    }

    if ($form->is_cancelled()) {
        $filters_cache->set("filter_users", "");
        $filters_cache->set("filter_category", "");
    }
    $form->display();
}

/**
 * Return time teacher connetion in a course
 *
 * @param $times
 * @param $courseid
 * @param $userid
 * @return lang_string|string
 * @throws coding_exception
 * @throws dml_exception
 */
function tool_tutor_follow_get_time_connection($times, $courseid, $userid)
{
    global $DB;

    if (isset($times[0])) {
        $dedication_manager = new dedication_time(
            $DB->get_record_sql("SELECT * FROM {course} WHERE id = :courseid", ['courseid' => $courseid]),
            $times[0]['after'],
            $times[0]['before']
        );
    } else {
        $dedication_manager = new dedication_time(
            $DB->get_record_sql("SELECT * FROM {course} WHERE id = :courseid", ['courseid' => $courseid]),
            0,
            time()
        );
    }

    $user = $DB->get_record_sql("SELECT * FROM {user} WHERE id = :userid", ['userid' => $userid]);
    $row = $dedication_manager->get_user_dedication($user, false);

    $time = 0;
    $count_sessions = 0;

    foreach ($row as $item) {
        $time += $item->dedicationtime;
        $count_sessions++;
    }

    if ($time != 0) {
        return get_string('dedicated_info', 'tool_tutor_follow', [
            'time' => format_time($time),
            'count' => $count_sessions
        ]);
    } else {
        return get_string('noaccess', 'tool_tutor_follow');
    }
}

/**
 * Return general data
 *
 * @return void
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function tool_tutor_follow_option4()
{
    global $OUTPUT;

    $data = json_decode(tool_tutor_follow_get_data('json_user_data', 'data_user'));
    if (!is_object($data)) {
        $data = new stdClass();
    }
    $data->lastejecution = tool_tutor_follow_get_lastime_execution_task('\tool_tutor_follow\task\data_user_tutor');

    echo $OUTPUT->render_from_template('tool_tutor_follow/option4/table', $data);

    echo " <br><hr> ";
    tool_tutor_follow_get_chart_principales_caracteristicas_teachers($data);
}

/**
 * Updated file, if the new config
 *
 * @param $content
 * @param $name_file
 * @param $area
 * @return void
 * @throws dml_exception
 * @throws file_exception
 * @throws stored_file_creation_exception
 */
function tool_tutor_follow_save_data_json($content, $name_file, $area)
{
    global $USER;

    $contextid = context_system::instance()->id;
    $component = 'tool_tutor_follow';
    $filearea = $area;
    $itemid = 0;
    $filepath = '/';
    $filename = $name_file;
    $filecontent = $content;

    $file_record = [
        'contextid' => $contextid,
        'component' => $component,
        'filearea' => $filearea,
        'itemid' => $itemid,
        'filepath' => $filepath,
        'filename' => $filename,
        'userid' => $USER->id
    ];

    $fs = get_file_storage();

    $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

    if ($file) {
        $file->delete();
    }

    $fs->create_file_from_string($file_record, $filecontent);
}

function tool_tutor_follow_option2()
{
    $form = new \tool_tutor_follow\form\send_report_weekly(
        action: new moodle_url('/admin/tool/tutor_follow/index.php', ['i' => 2])
    );
    if ($data = $form->get_data()) {
        $form->create_adhocs_notified(
            weekstart: $data->week,
            weekend: strtotime('+6 days 23:59:59', $data->week),
        );
    }
    $form->display();
}

/**
 * Third option
 *
 * @return void
 * @throws moodle_exception
 */
function tool_tutor_follow_option3() {
    global $OUTPUT, $CFG, $DB;

    require_once($CFG->libdir . '/tablelib.php');

    $page             = optional_param('page', 0, PARAM_INT);
    $perpageoptions   = [10, 20, 50, 100, 200, 500];
    $perpage          = optional_param('perpage', 20, PARAM_INT);
    if (!in_array($perpage, $perpageoptions, true)) {
        $perpage = 20;
    }
    $offset = $page * $perpage;
    $baseurl = new moodle_url('/admin/tool/tutor_follow/index.php', [
        'i'       => optional_param('i', 0, PARAM_INT),
        'page'    => $page,
        'perpage' => $perpage,
    ]);

    $table = new flexible_table('tool_tutor_follow_report_table');
    $table->define_baseurl($baseurl);
    $table->set_attribute('class', 'generaltable generalbox');
    $table->set_attribute('style', 'position: absolute; background-color: white;');

    $columns = [
        'selectreport',
        'status',
        'authorid',
        'title',
        'description',
        'viewdescription',
        'cc_email',
        'cco_email',
        'timecreated',
        'lasupdated'
    ];

    $masterheader = html_writer::empty_tag('input', [
        'type'  => 'checkbox',
        'class' => 'select-all-reports',
        'title' => get_string('selectall'),
    ]);

    $headers = [
        $masterheader,
        get_string('status', 'tool_tutor_follow'),
        get_string('authorid', 'tool_tutor_follow'),
        get_string('title', 'tool_tutor_follow'),
        get_string('description', 'tool_tutor_follow'),
        get_string('viewdescription', 'tool_tutor_follow'),
        get_string('cc_email', 'tool_tutor_follow'),
        get_string('cco_email', 'tool_tutor_follow'),
        get_string('timecreated', 'tool_tutor_follow'),
        get_string('lasupdated', 'tool_tutor_follow')
    ];

    $table->define_columns($columns);
    $table->define_headers($headers);

    $table->sortable(true, 'timecreated', SORT_DESC);
    $table->no_sorting('selectreport');
    $table->no_sorting('description');
    $table->no_sorting('viewdescription');
    $table->collapsible(true);
    $table->setup();

    $report_teacher = new \tool_tutor_follow\form\filter_reports(
        action: new moodle_url('/admin/tool/tutor_follow/index.php', ['i' => 3])
    );
    $report_teacher->display();

    $perpageurl = new moodle_url('/admin/tool/tutor_follow/index.php', ['i' => 3]);
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $perpageurl->out(false),
        'class'  => 'form-inline mb-3 d-flex justify-content-end align-items-center',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'i', 'value' => 3]);
    echo html_writer::tag('label', get_string('rowsperpage', 'tool_tutor_follow'), [
        'for'   => 'tool-tutor-follow-perpage',
        'class' => 'mr-2 mb-0',
    ]);
    $selectoptions = [];
    foreach ($perpageoptions as $option) {
        $selectoptions[$option] = $option;
    }
    echo html_writer::select($selectoptions, 'perpage', $perpage, false, [
        'id'       => 'tool-tutor-follow-perpage',
        'class'    => 'custom-select custom-select-sm',
        'onchange' => 'this.form.submit();',
    ]);
    echo html_writer::end_tag('form');

    $sendurl = new moodle_url('/admin/tool/tutor_follow/index.php', ['i' => 3]);
    echo '<form method="post" action="' . $sendurl->out(false) . '" id="tool-tutor-follow-send-selected-form">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

    echo html_writer::start_div('text-right mb-3');
    echo html_writer::tag(
        'button',
        '<i class="fa fa-paper-plane mr-1"></i> ' . get_string('sendreport', 'tool_tutor_follow'),
        [
            'type'   => 'submit',
            'name'   => 'action',
            'value'  => 'sendselected',
            'class'  => 'btn btn-primary mr-2',
            'id'     => 'tool-tutor-follow-send-selected',
            'style'  => 'display:none;',
        ]
    );
    echo html_writer::tag(
        'button',
        '<i class="fa fa-trash mr-1"></i> ' . get_string('deletereports', 'tool_tutor_follow'),
        [
            'type'   => 'submit',
            'name'   => 'action',
            'value'  => 'deleteselected',
            'class'  => 'btn btn-danger',
            'id'     => 'tool-tutor-follow-delete-selected',
            'style'  => 'display:none;',
        ]
    );
    echo html_writer::end_div();

    $sortcols = $table->get_sort_columns();
    $sort = key($sortcols) ?? 'timecreated';
    $dir  = current($sortcols) === SORT_ASC ? 'ASC' : 'DESC';

    $records = $report_teacher->get_reports_filter(
        $offset,
        $perpage,
        $sort,
        $dir
    );
    $total   = $report_teacher->count_reports_filter();

    $table->pagesize($perpage, $total);

    foreach ($records as $record) {

        $timecreated = !empty($record->timecreated)
            ? userdate($record->timecreated)
            : '-';

        $lasupdated = !empty($record->lasupdated)
            ? userdate($record->lasupdated)
            : '-';

        $record->cc_email = !empty($record->cc_email)
            ? tool_tutor_follow_get_listusers($record->cc_email)
            : '';

        $record->cco_email = !empty($record->cco_email)
            ? tool_tutor_follow_get_listusers($record->cco_email)
            : '';

        $profileurl = new moodle_url('/user/profile.php', [
            'id' => $record->authorid
        ]);

        $reporturl = new moodle_url('/admin/tool/tutor_follow/index.php', [
            'i'        => optional_param('i', 3, PARAM_INT),
            'reportid' => $record->id
        ]);

        $context = context_system::instance();
        $description = file_rewrite_pluginfile_urls(
            $record->description,
            'pluginfile.php',
            $context->id,
            'tool_tutor_follow',
            'description',
            $record->id
        );

        $author = fullname($DB->get_record('user', ['id' => $record->authorid]));

        $checkbox = html_writer::empty_tag('input', [
            'type'  => 'checkbox',
            'name'  => 'reportids[]',
            'value' => $record->id,
            'class' => 'select-report',
        ]);

        $viewdescbtn = html_writer::tag(
            'button',
            '<i class="fa fa-search-plus"></i>',
            [
                'type'             => 'button',
                'class'            => 'btn btn-info btn-sm view-description-btn',
                'data-title'       => $record->title,
                'data-description' => $description,
                'title'            => get_string('viewdescription', 'tool_tutor_follow'),
            ]
        );

        $row = [
            $checkbox,
            $report_teacher->statusoptions[$record->status],
            html_writer::link($profileurl, $author, ['target' => '_blank']),
            $record->title,
            shorten_text($description, 120),
            $viewdescbtn,
            $record->cc_email,
            $record->cco_email,
            $timecreated,
            $lasupdated,
            '
            <div class="d-flex flex-column" style="gap:4px;">
                <button type="button" data-id="' . $record->id . '" class="btn btn-secondary btn-sm edit-report">
                    <i class="fas fa-pen"></i>
                </button>
                <button type="button" data-id="' . $record->id . '" class="btn btn-danger btn-sm delete-report">
                    <i class="fas fa-trash"></i>
                </button>
                <a href="' . $reporturl . '" class="btn btn-info btn-sm view-report">
                    <i class="fas fa-eye"></i>
                </a>
            </div>'
        ];

        $table->add_data($row);
    }

    echo $OUTPUT->render_from_template('tool_tutor_follow/option3/table', []);
    $table->finish_output();

    echo '</form>';

    echo '
    <div id="tool-tutor-follow-desc-modal" style="display:none;position:fixed;inset:0;z-index:2147483646;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:90vw;max-height:85vh;width:900px;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,0.3);display:flex;flex-direction:column;overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #dee2e6;background:#f8f9fa;">
                <h4 id="tool-tutor-follow-desc-modal-title" style="margin:0;font-size:1.1rem;"></h4>
                <button type="button" id="tool-tutor-follow-desc-modal-close" class="btn btn-sm btn-light" aria-label="' . get_string('close', 'tool_tutor_follow') . '" style="font-size:1.3rem;line-height:1;padding:2px 10px;">&times;</button>
            </div>
            <div id="tool-tutor-follow-desc-modal-body" style="padding:18px;overflow:auto;"></div>
        </div>
    </div>
    <script>
    (function() {
        var modal = document.getElementById("tool-tutor-follow-desc-modal");
        var titleEl = document.getElementById("tool-tutor-follow-desc-modal-title");
        var bodyEl = document.getElementById("tool-tutor-follow-desc-modal-body");
        var closeBtn = document.getElementById("tool-tutor-follow-desc-modal-close");
        if (!modal) { return; }
        var open = function(title, html) {
            titleEl.textContent = title || "";
            bodyEl.innerHTML = html || "";
            modal.style.display = "flex";
            document.body.style.overflow = "hidden";
        };
        var close = function() {
            modal.style.display = "none";
            document.body.style.overflow = "";
        };
        closeBtn.addEventListener("click", close);
        modal.addEventListener("click", function(e) { if (e.target === modal) { close(); } });
        document.addEventListener("keydown", function(e) { if (e.key === "Escape") { close(); } });
        document.addEventListener("click", function(e) {
            var btn = e.target.closest(".view-description-btn");
            if (!btn) { return; }
            e.preventDefault();
            open(btn.getAttribute("data-title"), btn.getAttribute("data-description"));
        });
    })();
    </script>';
}


/**
 * Return list of users
 *
 * @param $list
 * @return string
 * @throws dml_exception
 * @throws moodle_exception
 */
function tool_tutor_follow_get_listusers($list)
{
    global $DB;
    $ccids = array_filter(array_map('intval', explode(',', $list)));
    $users = $DB->get_records_list('user', 'id', $ccids, '', 'id,firstname, lastname, email');
    return implode(
        ', ',
        array_map(function ($u) {
            $url = new moodle_url('/user/profile.php', ['id' => $u->id]);
            return "<a href='$url' target='_blank'>" . fullname($u) . " " . $u->email . "</a>";
        }, $users)
    );
}

/**
 * Return info of the file
 *
 * @param $name_file
 * @param $area
 * @return string|null
 * @throws dml_exception
 */
function tool_tutor_follow_get_data($name_file, $area)
{

    $contextid = context_system::instance()->id;
    $component = 'tool_tutor_follow';
    $filearea = $area;
    $itemid = 0;
    $filepath = '/';
    $filename = $name_file;

    $fs = get_file_storage();

    $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

    if ($file) {
        return $file->get_content();
    } else {
        return null;
    }
}

/**
 * Return las time of the ejecution of task
 *
 * @param $taskname
 * @return string
 * @throws dml_exception
 * @throws coding_exception
 */
function tool_tutor_follow_get_lastime_execution_task($taskname)
{
    global $DB;

    $sql = "SELECT * FROM {task_scheduled} WHERE classname = ?";
    $last_run = $DB->get_record_sql($sql, [$taskname]);

    $value = get_string('notupdated', 'tool_tutor_follow');

    if ($last_run->lastruntime) {
        $value = userdate($last_run->lastruntime);
    }
    return $value;
}

/**
 * Retornar la cantidad de estudiantes
 * @param $courseid
 * @return void
 * @throws \dml_exception
 */
function tool_tutor_follow_get_count_students($courseid)
{
    global $DB;

    $sql = "SELECT COUNT(DISTINCT u . id) as active_students
        FROM {user_enrolments} ue
        JOIN {enrol} e ON ue . enrolid = e . id
        JOIN {user} u ON ue . userid = u . id
        JOIN {role_assignments} ra ON ra . userid = u . id
        JOIN {context} ctx ON ctx . id = ra . contextid
        JOIN {role} r ON ra . roleid = r . id
        WHERE e . courseid = :courseid
            and ue . status = :status
            and u . deleted = 0
            and ctx . contextlevel = 50
            and r . archetype = :archetype";

    $params = [
        'courseid' => $courseid,
        'status' => 0,
        'archetype' => 'student',
    ];

    return $DB->get_field_sql($sql, $params);
}

/**
 * Grafic of the principal caracteristics of the user
 *
 * @param $data
 * @return void
 * @throws coding_exception
 */
function tool_tutor_follow_get_chart_principales_caracteristicas_teachers($data)
{
    global $OUTPUT;

    $chart = new chart_bar();

    $chart->set_title(get_string('globalfeatures', 'tool_tutor_follow'));
    $chart->set_horizontal(true);

    $values = array_map(function ($user) {
        return $user->all_students;
    }, $data->users);

    $teacher = array_map(function ($user) {
        return "$user->firstname $user->lastname";
    }, $data->users);

    $numcursos = array_map(function ($user) {
        return $user->numcursos;
    }, $data->users);

    $activities_for_calification = array_map(function ($user) {
        return $user->activities_for_calification;
    }, $data->users);

    $activities_no_grade = array_map(function ($user) {
        return $user->activities_no_grade;
    }, $data->users);

    $chart->add_series(new chart_series(get_string('studentsincharge', 'tool_tutor_follow'), $values));
    $chart->add_series(new chart_series(get_string('enrolledcourses', 'tool_tutor_follow'), $numcursos));
    $chart->add_series(new chart_series(get_string('gradableactivities', 'tool_tutor_follow'), $activities_for_calification));
    $chart->add_series(new chart_series(get_string('automaticactivities', 'tool_tutor_follow'), $activities_no_grade));

    $chart->set_labels($teacher);

    echo $OUTPUT->render($chart);
}

/**
 * Pink the table of the courses
 *
 * @param $courseid
 * @param $endtime
 * @return void
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function tool_tutor_follow_details_table_course($courseid, $endtime)
{
    global $OUTPUT, $DB, $PAGE;

    $courseinfo = $DB->get_record("tool_tutor_follow", [
        "instance_id" => $courseid,
        "type" => data_user_tutor::DATA_TYPE['COURSE']
    ]);

    if (!$courseinfo) {
        return;
    }

    $info = json_decode(base64_decode($courseinfo->datajson));
    if ($endtime) {
        foreach ($info->forums as $forum) {
            //Calificaciones foros
            $grades = [];
            $count = 0;
            $stadistics_grades = [];
            foreach ($forum->grades_process as $grade) {
                if ($grade->time_created_forum <= $endtime) {
                    if ($grade->timemodified < $endtime) {
                        $grades[] = $grade;
                        $found = false;
                        unset($obj);
                        foreach ($stadistics_grades as $obj) {
                            if ($obj->grade == $grade->grade) {
                                $obj->count++;
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $obj = new stdClass();
                            $obj->grade = $grade->grade;
                            $obj->count = 1;
                            $stadistics_grades[] = $obj;
                        }
                    }
                    $count++;
                }
            }
            $forum->grades_process = $grades;
            $forum->total_posts = $count;
            $forum->pending_grades = $forum->total_posts - sizeof($forum->grades_process);
            $forum->stadistics_grades = $stadistics_grades;
            $forum->json_stadistics_grades = json_encode($stadistics_grades);
        }

        foreach ($info->assigns as $assign) {
            //Grades of assign
            $grades = [];
            $count = 0;
            $stadistics_grades = [];
            foreach ($assign->grades_process as $grade) {
                if ($grade->time_created_assign <= $endtime) {
                    if ($grade->timemodified < $endtime) {
                        $grades[] = $grade;
                        $found = false;
                        unset($obj);
                        foreach ($stadistics_grades as $obj) {
                            if ($obj->grade == $grade->grade) {
                                $obj->count++;
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $obj = new stdClass();
                            $obj->grade = $grade->grade;
                            $obj->count = 1;
                            $stadistics_grades[] = $obj;
                        }
                    }
                    $count++;
                }
                $assign->grades_process = $grades;
                $assign->total_submissions = $count;
                $assign->pending_grades = $assign->total_submissions - sizeof($assign->grades_process);
                $assign->stadistics_grades = $stadistics_grades;
                $assign->json_stadistics_grades = json_encode($stadistics_grades);
            }
        }
    }

    // ── Course hero card ──────────────────────────────────────────────────
    $info->course->url_course = new moodle_url('/course/view.php', ['id' => $info->course->id]);

    $kpi_defs = [
        ['icon' => 'fa-file-alt',     'key' => 'assignments',   'val' => $info->course->total_assign,         'cls' => ''],
        ['icon' => 'fa-comments',     'key' => 'forums',        'val' => $info->course->total_forum,          'cls' => ''],
        ['icon' => 'fa-users',        'key' => 'students',      'val' => $info->course->students,             'cls' => ''],
        ['icon' => 'fa-upload',       'key' => 'submissions',   'val' => $info->course->total_assignments,    'cls' => ''],
        ['icon' => 'fa-comment-alt',  'key' => 'contributions', 'val' => $info->course->total_posts,          'cls' => ''],
        ['icon' => 'fa-check-circle', 'key' => 'graded',        'val' => $info->course->total_grades,         'cls' => 'dtl-kpi-success'],
        ['icon' => 'fa-clock',        'key' => 'pendinggrading','val' => $info->course->total_pending_grades, 'cls' => 'dtl-kpi-danger'],
    ];
    $kpi_html = '';
    foreach ($kpi_defs as $k) {
        $kpi_html .= '<div class="dtl-kpi-pill ' . $k['cls'] . '">'
            . '<i class="fa ' . $k['icon'] . '"></i>'
            . '<span class="dtl-kpi-val">' . $k['val'] . '</span>'
            . '<span>' . get_string($k['key'], 'tool_tutor_follow') . '</span>'
            . '</div>';
    }

    echo '<div class="dtl-hero">'
        . '<p class="dtl-hero-meta">'
        . get_string('lastupdate', 'tool_tutor_follow') . ': <strong>'
        . userdate($info->timemodified, get_string('strftimedaydatetime', 'langconfig'))
        . '</strong></p>'
        . '<a class="dtl-hero-title" href="' . $info->course->url_course . '" target="_blank">'
        . '<i class="fa fa-external-link-alt fa-sm"></i>'
        . $info->course->shortname . ' &mdash; '
        . $info->course->fullname
        . '</a>'
        . '<div class="dtl-kpi-row">' . $kpi_html . '</div>'
        . '</div>';

    // ── General features chart ────────────────────────────────────────────
    $chart = new chart_bar();
    $chart->set_title(get_string('generalfeatures', 'tool_tutor_follow'));
    $chart->set_horizontal(true);
    $chart->add_series(new chart_series(
        $info->course->shortname . ' - ' . $info->course->fullname,
        [
            $info->course->total_assign,
            $info->course->total_forum,
            $info->course->students,
            $info->course->total_assignments,
            $info->course->total_posts,
            $info->course->total_grades,
            $info->course->total_pending_grades,
        ]
    ));
    $chart->set_labels([
        get_string('assignments', 'tool_tutor_follow'),
        get_string('forums', 'tool_tutor_follow'),
        get_string('students', 'tool_tutor_follow'),
        get_string('submissions', 'tool_tutor_follow'),
        get_string('contributions', 'tool_tutor_follow'),
        get_string('graded', 'tool_tutor_follow'),
        get_string('pendinggrading', 'tool_tutor_follow'),
    ]);

    echo '<div class="dtl-card">'
        . '<div class="dtl-card-head">'
        . '<div class="dtl-card-head-icon bg-primary"><i class="fa fa-chart-bar"></i></div>'
        . get_string('generalfeatures', 'tool_tutor_follow')
        . '</div><div class="dtl-card-body">';
    echo $OUTPUT->render($chart);
    echo '</div></div>';

    // ── Dedication times ──────────────────────────────────────────────────
    $data = json_decode(tool_tutor_follow_get_data('json_user_data', 'data_user'));
    $teachers = [];
    foreach ($data->users as $user) {
        $shortnameBuscado = $info->course->shortname;
        $result = array_filter($user->cursos, function ($obj) use ($shortnameBuscado) {
            return isset($obj->shortname) && $obj->shortname === $shortnameBuscado;
        });
        if ($result) {
            $teachers[] = $user;
        }
    }

    $chart = new chart_bar();
    $chart->set_title(get_string('dedicationtimes', 'tool_tutor_follow'));
    $chart->set_horizontal(true);

    echo '<div class="dtl-card">'
        . '<div class="dtl-card-head">'
        . '<div class="dtl-card-head-icon bg-secondary"><i class="fa fa-clock"></i></div>'
        . get_string('dedicationtimes', 'tool_tutor_follow')
        . '</div>'
        . '<div style="overflow-x:auto">'
        . '<table class="dtl-tbl"><thead><tr>'
        . '<th>' . get_string('name', 'tool_tutor_follow') . '</th>'
        . '<th>' . get_string('startdate', 'tool_tutor_follow') . '</th>'
        . '<th>' . get_string('dedicationminutes', 'tool_tutor_follow') . '</th>'
        . '</tr></thead><tbody>';

    foreach ($teachers as $teacher) {
        if ($endtime) {
            $dedication_manager = new dedication_time($info->course, '0', $endtime);
        } else {
            $dedication_manager = new dedication_time($info->course, '0', time());
        }
        $accestime = $dedication_manager->get_user_dedication($teacher, false);

        $count_access = 0;
        $minutes      = 0;
        $teacher_link = '<a href="' . $teacher->perfil_url . '" target="_blank">'
            . $teacher->firstname . ' ' . $teacher->lastname . '</a>';

        if ($accestime) {
            foreach ($accestime as $access) {
                $count_access++;
                $mins = round($access->dedicationtime / 60, 1);
                echo '<tr><td>' . $teacher_link . '</td>'
                    . '<td>' . userdate($access->start_date) . '</td>'
                    . '<td>' . $mins . '</td></tr>';
                $minutes += $mins;
            }
        } else {
            echo '<tr><td>' . $teacher_link . '</td>'
                . '<td>' . get_string('never', 'tool_tutor_follow') . '</td>'
                . '<td>0</td></tr>';
        }

        $promedio = ($count_access != 0) ? round($minutes / $count_access, 1) : 0;
        $chart->add_series(new chart_series(
            $teacher->firstname . ' ' . $teacher->lastname,
            [$count_access, $minutes, $promedio]
        ));
    }

    $chart->set_labels([
        get_string('accesses', 'tool_tutor_follow'),
        get_string('dedicationminutes', 'tool_tutor_follow'),
        get_string('average_dedication', 'tool_tutor_follow'),
    ]);

    echo '</tbody></table></div>'
        . '<div class="dtl-card-body">';
    echo $OUTPUT->render($chart);
    echo '</div></div>';

    $info->number_grades_string = get_string('gradecount', 'tool_tutor_follow');
    echo $OUTPUT->render_from_template('tool_tutor_follow/details/course', $info);

    // ── AMD charts: activity status + coverage donut + timeline ──────────
    $chart_forums = [];
    foreach ($info->forums as $f) {
        $graded_f = ($f->total_posts ?? 0) - ($f->pending_grades ?? 0);
        $chart_forums[] = [
            'name'             => $f->name,
            'participated'     => $f->total_posts         ?? 0,
            'graded'           => max(0, $graded_f),
            'pending'          => $f->pending_grades      ?? 0,
            'without_feedback' => $f->count_without_feedback ?? 0,
        ];
    }
    $chart_assigns = [];
    foreach ($info->assigns as $a) {
        $graded_a = ($a->total_submissions ?? 0) - ($a->pending_grades ?? 0);
        $chart_assigns[] = [
            'name'             => $a->name,
            'submitted'        => $a->total_submissions   ?? 0,
            'graded'           => max(0, $graded_a),
            'pending'          => $a->pending_grades      ?? 0,
            'without_feedback' => $a->count_without_feedback ?? 0,
        ];
    }

    // Build weekly timeline from grades_process
    $timeline_buckets = [];
    foreach (array_merge((array)$info->forums, (array)$info->assigns) as $act) {
        $is_forum = isset($act->total_posts);
        foreach ($act->grades_process as $g) {
            $ts_sub   = $is_forum ? ($g->time_created_forum  ?? 0) : ($g->time_created_assign ?? 0);
            $ts_grade = $g->timemodified ?? 0;
            $week_sub   = $ts_sub   ? date('Y-W', $ts_sub)   : null;
            $week_grade = $ts_grade ? date('Y-W', $ts_grade) : null;
            if ($week_sub) {
                if (!isset($timeline_buckets[$week_sub])) {
                    $timeline_buckets[$week_sub] = ['ts' => $ts_sub, 'label' => date('d/m/Y', $ts_sub), 'submissions' => 0, 'grades' => 0, 'without_feedback' => 0];
                }
                $timeline_buckets[$week_sub]['submissions']++;
            }
            if ($week_grade) {
                if (!isset($timeline_buckets[$week_grade])) {
                    $timeline_buckets[$week_grade] = ['ts' => $ts_grade, 'label' => date('d/m/Y', $ts_grade), 'submissions' => 0, 'grades' => 0, 'without_feedback' => 0];
                }
                $timeline_buckets[$week_grade]['grades']++;
            }
        }
        foreach ($act->without_feedback ?? [] as $wf) {
            $ts_wf = $wf->timemodified ?? 0;
            if (!$ts_wf) {
                continue;
            }
            $week_wf = date('Y-W', $ts_wf);
            if (!isset($timeline_buckets[$week_wf])) {
                $timeline_buckets[$week_wf] = ['ts' => $ts_wf, 'label' => date('d/m/Y', $ts_wf), 'submissions' => 0, 'grades' => 0, 'without_feedback' => 0];
            }
            $timeline_buckets[$week_wf]['without_feedback']++;
        }
    }
    usort($timeline_buckets, fn($a, $b) => $a['ts'] - $b['ts']);

    $total_without_feedback = 0;
    foreach (array_merge($chart_forums, $chart_assigns) as $item) {
        $total_without_feedback += $item['without_feedback'];
    }

    $chart_data = [
        'forums'                  => $chart_forums,
        'assigns'                 => $chart_assigns,
        'total_graded'            => $info->course->total_grades         ?? 0,
        'total_pending'           => $info->course->total_pending_grades ?? 0,
        'total_without_feedback'  => $total_without_feedback,
        'timeline'                => array_values($timeline_buckets),
        'str_submitted'           => get_string('submissions',                   'tool_tutor_follow'),
        'str_graded'              => get_string('graded',                        'tool_tutor_follow'),
        'str_pending'             => get_string('pending_grades',                'tool_tutor_follow'),
        'str_without_feedback'    => get_string('report_graded_without_feedback','tool_tutor_follow'),
        'str_activity_status'     => get_string('chart_activity_status',         'tool_tutor_follow'),
        'str_coverage'     => get_string('chart_coverage',       'tool_tutor_follow'),
        'str_timeline'     => get_string('chart_timeline',       'tool_tutor_follow'),
        'str_submissions_acc' => get_string('chart_submissions_acc', 'tool_tutor_follow'),
        'str_grades_acc'   => get_string('chart_grades_acc',     'tool_tutor_follow'),
        'str_viewtable'    => get_string('viewtable',            'tool_tutor_follow'),
        'str_type'         => get_string('type',                 'tool_tutor_follow'),
        'str_nameactivity' => get_string('nameactivity',         'tool_tutor_follow'),
        'str_startdate'    => get_string('startdate',            'tool_tutor_follow'),
        'str_forum_type'   => get_string('forum',                'tool_tutor_follow'),
        'str_assign_type'  => get_string('assignment',           'tool_tutor_follow'),
    ];

    $cid     = $info->course->id;
    $str_vt  = get_string('viewtable', 'tool_tutor_follow');

    echo '<script type="application/json" id="dtl-course-data-' . $cid . '">'
        . json_encode($chart_data) . '</script>';

    echo '<div class="modal fade" id="dtl-cmodal-' . $cid . '" tabindex="-1" aria-hidden="true">'
        . '<div class="modal-dialog modal-xl"><div class="modal-content">'
        . '<div class="modal-header" style="background:var(--bs-primary);color:#fff">'
        . '<h5 class="modal-title" id="dtl-cmodal-' . $cid . '-title"></h5>'
        . '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="' . get_string('close', 'tool_tutor_follow') . '"></button>'
        . '</div>'
        . '<div class="modal-body" id="dtl-cmodal-' . $cid . '-body" style="overflow-x:auto;padding:1.5rem"></div>'
        . '</div></div></div>';

    echo '<div class="dtl-card">'
        . '<div class="dtl-card-head">'
        . '<div class="dtl-card-head-icon bg-primary"><i class="fa fa-chart-line"></i></div>'
        . get_string('chart_activity_status', 'tool_tutor_follow')
        . '<button class="btn btn-outline-light btn-sm dtl-tbl-btn" style="margin-left:auto;flex-shrink:0"'
        . ' data-chart="activity" data-modal="dtl-cmodal-' . $cid . '">'
        . '<i class="fa fa-table fa-xs mr-1"></i>' . $str_vt
        . '</button>'
        . '</div><div class="dtl-card-body">'
        . '<div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start">'
        . '<div style="min-height:' . max(220, count($chart_forums) * 44 + count($chart_assigns) * 44 + 60) . 'px">'
        . '<canvas id="dtl-act-chart-' . $cid . '"></canvas>'
        . '</div>'
        . '<div style="height:260px"><canvas id="dtl-cov-chart-' . $cid . '"></canvas></div>'
        . '</div>'
        . '</div></div>';

    if (!empty($timeline_buckets)) {
        echo '<div class="dtl-card">'
            . '<div class="dtl-card-head">'
            . '<div class="dtl-card-head-icon bg-secondary"><i class="fa fa-calendar-alt"></i></div>'
            . get_string('chart_timeline', 'tool_tutor_follow')
            . '<button class="btn btn-outline-light btn-sm dtl-tbl-btn" style="margin-left:auto;flex-shrink:0"'
            . ' data-chart="timeline" data-modal="dtl-cmodal-' . $cid . '">'
            . '<i class="fa fa-table fa-xs mr-1"></i>' . $str_vt
            . '</button>'
            . '</div><div class="dtl-card-body"><div style="height:260px">'
            . '<canvas id="dtl-time-chart-' . $cid . '"></canvas>'
            . '</div></div></div>';
    }

    $PAGE->requires->js_call_amd('tool_tutor_follow/course_charts', 'init', [$cid]);
}

/**
 * Render user detail page (all courses taught by a given user).
 *
 * @param int        $userid
 * @param int|null   $endtime
 * @return void
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function tool_tutor_follow_details_table_user(int $userid, ?int $endtime): void
{
    global $OUTPUT, $DB, $PAGE;

    $data = json_decode(tool_tutor_follow_get_data('json_user_data', 'data_user'));

    if (!is_object($data) || empty($data->users)) {
        return;
    }

    $user = null;
    foreach ($data->users as $u) {
        if ((int)$u->id === $userid) {
            $user = $u;
            break;
        }
    }

    if (!$user) {
        return;
    }

    $user_db = $DB->get_record('user', ['id' => $userid]);

    // ── Pre-load course records ───────────────────────────────────────────
    $course_infos            = [];
    $total_pending           = 0;
    $total_graded            = 0;
    $total_assigns_count     = 0;
    $total_forums_count      = 0;
    $total_submissions       = 0;
    $total_replicas          = 0;
    $total_without_feedback  = 0;

    foreach ($user->cursos as $course) {
        $rec = $DB->get_record('tool_tutor_follow', [
            'instance_id' => $course->id,
            'type'        => data_user_tutor::DATA_TYPE['COURSE'],
        ]);
        if (!$rec) {
            continue;
        }
        $info = json_decode(base64_decode($rec->datajson));
        $total_pending       += $info->course->total_pending_grades ?? 0;
        $total_graded        += $info->course->total_grades         ?? 0;
        $total_assigns_count += $info->course->total_assign         ?? 0;
        $total_forums_count  += $info->course->total_forum          ?? 0;
        $total_submissions   += $info->course->total_assignments    ?? 0;
        $total_replicas      += $info->course->total_posts          ?? 0;
        foreach ($info->forums ?? [] as $f) {
            $total_without_feedback += $f->count_without_feedback ?? 0;
        }
        foreach ($info->assigns ?? [] as $a) {
            $total_without_feedback += $a->count_without_feedback ?? 0;
        }
        $course_infos[$course->id] = $info;
    }

    // ── Dedication per course ─────────────────────────────────────────────
    $dedication_courses = [];
    $cutoff = $endtime ?? time();

    foreach ($user->cursos as $course) {
        $course_record    = get_course($course->id);
        $dedication_mgr   = new dedication_time($course_record, 0, $cutoff);
        $rows             = $dedication_mgr->get_user_dedication($user_db, false);

        $minutes  = 0;
        $sessions = 0;
        foreach ($rows as $row) {
            $minutes += round($row->dedicationtime / 60, 1);
            $sessions++;
        }

        $dedication_courses[] = [
            'course_name'         => $course->shortname,
            'course_fullname'     => $course->fullname,
            'dedication_minutes'  => $minutes,
            'dedication_sessions' => $sessions,
            'dedication_average'  => $sessions > 0 ? round($minutes / $sessions, 1) : 0,
        ];
    }

    $has_dedication = !empty(array_filter($dedication_courses, fn($c) => $c['dedication_sessions'] > 0));

    $kpi_total_minutes = array_sum(array_column($dedication_courses, 'dedication_minutes'));
    $kpi_pct_graded    = ($total_graded + $total_pending) > 0
        ? round($total_graded / ($total_graded + $total_pending) * 100) : 0;
    $kpi_dedication_display = $kpi_total_minutes >= 60
        ? round($kpi_total_minutes / 60, 1) . ' h'
        : round($kpi_total_minutes, 1) . ' min';

    // ── Render user mustache (hero + dedication chart) ────────────────────
    $profile_url = new moodle_url('/user/profile.php', ['id' => $user->id]);

    $tpl = new stdClass();
    $tpl->userid              = $userid;
    $tpl->name                = $user->firstname . ' ' . $user->lastname;
    $tpl->email               = $user->email;
    $tpl->picture             = $user->picture;
    $tpl->profile_url         = $profile_url->out();
    $tpl->lastacces_text      = $user->lastacces_text;
    $tpl->kpi_numcourses         = $user->numcursos;
    $tpl->kpi_students           = $user->all_students;
    $tpl->kpi_assigns_count      = $total_assigns_count;
    $tpl->kpi_forums_count       = $total_forums_count;
    $tpl->kpi_submissions        = $total_submissions;
    $tpl->kpi_replicas           = $total_replicas;
    $tpl->kpi_graded             = $total_graded;
    $tpl->kpi_pending            = $total_pending;
    $tpl->kpi_without_feedback   = $total_without_feedback;
    $tpl->kpi_total_minutes      = round($kpi_total_minutes, 1);
    $tpl->kpi_dedication_display = $kpi_dedication_display;
    $tpl->kpi_pct_graded         = $kpi_pct_graded;
    $tpl->dedication_courses  = $dedication_courses;
    $tpl->json_dedication     = json_encode($dedication_courses);
    $tpl->has_dedication      = $has_dedication;
    $tpl->str_dedicationminutes   = get_string('dedicationminutes',   'tool_tutor_follow');
    $tpl->str_accesses            = get_string('accesses',            'tool_tutor_follow');
    $tpl->str_dedicationpercourse = get_string('dedicationpercourse', 'tool_tutor_follow');
    $tpl->str_average_dedication  = get_string('average_dedication',  'tool_tutor_follow');

    echo $OUTPUT->render_from_template('tool_tutor_follow/details/user', $tpl);

    // ── AMD charts for user (load bar + donut + radar) ────────────────────
    $total_forums  = 0;
    $total_assigns = 0;
    $user_chart_courses = [];
    foreach ($user->cursos as $course) {
        if (!isset($course_infos[$course->id])) {
            continue;
        }
        $ci        = $course_infos[$course->id];
        $c_pending          = 0;
        $c_posts            = 0;
        $c_subs             = 0;
        $c_without_feedback = 0;
        foreach ($ci->forums as $f) {
            $c_pending          += $f->pending_grades        ?? 0;
            $c_posts            += $f->total_posts           ?? 0;
            $c_without_feedback += $f->count_without_feedback ?? 0;
        }
        foreach ($ci->assigns as $a) {
            $c_pending          += $a->pending_grades         ?? 0;
            $c_subs             += $a->total_submissions      ?? 0;
            $c_without_feedback += $a->count_without_feedback ?? 0;
        }
        $c_total_all = $c_posts + $c_subs;
        $c_graded    = max(0, $c_total_all - $c_pending);
        $c_forums    = count((array)$ci->forums);
        $c_assigns   = count((array)$ci->assigns);
        $total_forums  += $c_forums;
        $total_assigns += $c_assigns;

        $ded_min = 0;
        foreach ($dedication_courses as $dc) {
            if ($dc['course_name'] === $course->shortname) {
                $ded_min = $dc['dedication_minutes'];
                break;
            }
        }

        $user_chart_courses[] = [
            'shortname'          => $course->shortname,
            'fullname'           => $course->fullname,
            'url_course'         => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(),
            'details_url'        => (new moodle_url('/admin/tool/tutor_follow/details.php', ['courseid' => $course->id]))->out(),
            'students'           => $ci->course->students ?? 0,
            'graded'             => $c_graded,
            'pending'            => $c_pending,
            'pct'                => $c_total_all > 0 ? (int)round($c_graded / $c_total_all * 100) : 0,
            'forums'             => $c_forums,
            'assigns'            => $c_assigns,
            'connection_minutes' => $ded_min,
            'total_posts'        => $c_posts,
            'total_submissions'  => $c_subs,
            'without_feedback'   => $c_without_feedback,
        ];
    }

    $user_chart_data = [
        'courses'                  => $user_chart_courses,
        'total_forums'             => $total_forums,
        'total_assigns'            => $total_assigns,
        'str_students'             => get_string('students',                 'tool_tutor_follow'),
        'str_graded'               => get_string('graded',                            'tool_tutor_follow'),
        'str_pending'              => get_string('pending_grades',                  'tool_tutor_follow'),
        'str_without_feedback'     => get_string('report_graded_without_feedback',  'tool_tutor_follow'),
        'str_forums'               => get_string('forums',                          'tool_tutor_follow'),
        'str_assigns'              => get_string('assignments',              'tool_tutor_follow'),
        'str_load_title'           => get_string('chart_load_title',         'tool_tutor_follow'),
        'str_composition_title'    => get_string('chart_composition_title',  'tool_tutor_follow'),
        'str_radar_title'          => get_string('chart_radar_title',        'tool_tutor_follow'),
        'str_pct_graded'           => get_string('chart_pct_graded',         'tool_tutor_follow'),
        'str_connection'           => get_string('chart_connection',         'tool_tutor_follow'),
        'str_activities'           => get_string('chart_activities',         'tool_tutor_follow'),
        'str_viewtable'            => get_string('viewtable',                'tool_tutor_follow'),
        'str_course'               => get_string('course',                   'tool_tutor_follow'),
    ];

    $str_vtu = get_string('viewtable', 'tool_tutor_follow');

    echo '<script type="application/json" id="dtl-user-data-' . $userid . '">'
        . json_encode($user_chart_data) . '</script>';

    echo '<div class="modal fade" id="dtl-umodal-' . $userid . '" tabindex="-1" aria-hidden="true">'
        . '<div class="modal-dialog modal-xl"><div class="modal-content">'
        . '<div class="modal-header" style="background:var(--bs-primary);color:#fff">'
        . '<h5 class="modal-title" id="dtl-umodal-' . $userid . '-title"></h5>'
        . '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="' . get_string('close', 'tool_tutor_follow') . '"></button>'
        . '</div>'
        . '<div class="modal-body" id="dtl-umodal-' . $userid . '-body" style="overflow-x:auto;padding:1.5rem"></div>'
        . '</div></div></div>';

    $bar_height = max(260, count($user_chart_courses) * 55 + 80);
    echo '<div class="dtl-card">'
        . '<div class="dtl-card-head">'
        . '<div class="dtl-card-head-icon bg-primary"><i class="fa fa-chart-bar"></i></div>'
        . get_string('chart_load_title', 'tool_tutor_follow')
        . '<button class="btn btn-outline-light btn-sm dtl-tbl-btn" style="margin-left:auto;flex-shrink:0"'
        . ' data-chart="load" data-modal="dtl-umodal-' . $userid . '">'
        . '<i class="fa fa-table fa-xs mr-1"></i>' . $str_vtu
        . '</button>'
        . '</div><div class="dtl-card-body">'
        . '<div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start">'
        . '<div style="min-height:' . $bar_height . 'px"><canvas id="dtl-load-chart-' . $userid . '"></canvas></div>'
        . '<div style="height:260px"><canvas id="dtl-donut-chart-' . $userid . '"></canvas></div>'
        . '</div>'
        . '</div></div>';

    echo '<div class="dtl-card">'
        . '<div class="dtl-card-head">'
        . '<div class="dtl-card-head-icon bg-secondary"><i class="fa fa-spider"></i></div>'
        . get_string('chart_radar_title', 'tool_tutor_follow')
        . '<button class="btn btn-outline-light btn-sm dtl-tbl-btn" style="margin-left:auto;flex-shrink:0"'
        . ' data-chart="radar" data-modal="dtl-umodal-' . $userid . '">'
        . '<i class="fa fa-table fa-xs mr-1"></i>' . $str_vtu
        . '</button>'
        . '</div><div class="dtl-card-body"><div style="height:360px">'
        . '<canvas id="dtl-radar-chart-' . $userid . '"></canvas>'
        . '</div></div></div>';

    $PAGE->requires->js_call_amd('tool_tutor_follow/user_charts', 'init', [$userid]);

    // ── Courses summary table ─────────────────────────────────────────────
    $str_shortname        = get_string('shortname',                    'tool_tutor_follow');
    $str_students         = get_string('students',                     'tool_tutor_follow');
    $str_forums           = get_string('forums',                       'tool_tutor_follow');
    $str_assigns          = get_string('assignments',                  'tool_tutor_follow');
    $str_graded           = get_string('graded',                       'tool_tutor_follow');
    $str_pending          = get_string('pending_grades',               'tool_tutor_follow');
    $str_without_feedback = get_string('report_graded_without_feedback','tool_tutor_follow');
    $str_dedmin           = get_string('dedicationminutes',            'tool_tutor_follow');
    $str_showdet          = get_string('showdetails',                  'tool_tutor_follow');
    $str_courses          = get_string('courses',                      'tool_tutor_follow');
    $str_total_subs       = get_string('total_submissions',            'tool_tutor_follow');
    $str_total_posts      = get_string('total_participations',         'tool_tutor_follow');

    echo '<div class="dtl-card">'
        . '<div class="dtl-card-head">'
        . '<div class="dtl-card-head-icon bg-primary"><i class="fa fa-list-ul"></i></div>'
        . $str_courses
        . '</div>'
        . '<div style="overflow-x:auto"><table class="dtl-tbl"><thead><tr>'
        . '<th>#</th>'
        . '<th>' . $str_shortname   . '</th>'
        . '<th>' . $str_students    . '</th>'
        . '<th>' . $str_forums      . '</th>'
        . '<th>' . $str_assigns     . '</th>'
        . '<th>' . $str_total_posts . '</th>'
        . '<th>' . $str_total_subs  . '</th>'
        . '<th>' . $str_graded            . '</th>'
        . '<th>' . $str_pending           . '</th>'
        . '<th>' . $str_without_feedback  . '</th>'
        . '<th>' . $str_dedmin            . '</th>'
        . '<th></th>'
        . '</tr></thead><tbody>';

    $row_num = 1;
    foreach ($user_chart_courses as $row) {
        $pct = $row['pct'];
        $pct_bar = '<div style="display:flex;align-items:center;gap:.4rem;min-width:120px">'
            . '<div style="flex:1;background:#e9ecef;border-radius:4px;height:8px;overflow:hidden">'
            . '<div style="width:' . $pct . '%;background:var(--bs-primary);height:100%;border-radius:4px"></div>'
            . '</div>'
            . '<small style="white-space:nowrap;color:var(--bs-primary);font-weight:700">' . $pct . '%</small>'
            . '</div>';

        $pending_cell = $row['pending'] > 0
            ? '<span class="dtl-badge dtl-badge-red"><i class="fa fa-clock fa-xs"></i> ' . $row['pending'] . '</span>'
            : '<span class="dtl-badge dtl-badge-teal"><i class="fa fa-check fa-xs"></i> 0</span>';

        $wf = $row['without_feedback'] ?? 0;
        $without_feedback_cell = $wf > 0
            ? '<span class="dtl-badge dtl-badge-gray"><i class="fa fa-comment-slash fa-xs"></i> ' . $wf . '</span>'
            : '<span class="dtl-badge dtl-badge-teal"><i class="fa fa-check fa-xs"></i> 0</span>';

        echo '<tr>'
            . '<td>' . $row_num++ . '</td>'
            . '<td>'
            .   '<a href="' . $row['url_course'] . '" target="_blank"><strong>' . $row['shortname'] . '</strong></a>'
            .   '<br><small class="text-muted">' . $row['fullname'] . '</small>'
            . '</td>'
            . '<td class="text-center">' . $row['students']          . '</td>'
            . '<td class="text-center">' . $row['forums']            . '</td>'
            . '<td class="text-center">' . $row['assigns']           . '</td>'
            . '<td class="text-center">' . $row['total_posts']       . '</td>'
            . '<td class="text-center">' . $row['total_submissions']  . '</td>'
            . '<td>' . $pct_bar . '</td>'
            . '<td>' . $pending_cell . '</td>'
            . '<td>' . $without_feedback_cell . '</td>'
            . '<td class="text-right">' . $row['connection_minutes'] . ' min</td>'
            . '<td><a href="' . $row['details_url'] . '" class="btn btn-primary btn-sm" style="color:#fff;white-space:nowrap">'
            .   '<i class="fa fa-external-link-alt fa-xs mr-1"></i>' . $str_showdet
            . '</a></td>'
            . '</tr>';
    }

    echo '</tbody></table></div></div>';
}
