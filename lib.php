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

    echo "<p>" . get_string('lastupdate', 'tool_tutor_follow') . ": <b class='text-danger'>" . $data->lastejecution . "</b></p>";

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
                    ($userFilterActive && $categoryFilterActive &&
                        (!in_array($value->id, $filters->user) || !in_array($curso->category, $filters->category))) || // Ambos activos, pero falla uno
                    ($userFilterActive && !in_array($value->id, $filters->user)) || // Solo filtro usuario activo
                    ($categoryFilterActive && !in_array($curso->category, $filters->category)) // Solo filtro categoría activo
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

}

/**
 * Third option
 *
 * @return void
 * @throws moodle_exception
 */
function tool_tutor_follow_option3()
{
    global $OUTPUT, $CFG, $DB;
    require_once($CFG->libdir . '/tablelib.php');

    $baseurl = new moodle_url('/admin/tool/tutor_follow/index.php', [
        'i' => optional_param('i', 0, PARAM_INT)
    ]);

    $table = new flexible_table('tool_tutor_follow_report_table');
    $table->define_baseurl($baseurl);
    $table->set_attribute('style', 'position:absolute; background:white;');
    $columns = [
        'status',
        'authorid',
        'title',
        'description',
        'cc_email',
        'cco_email',
        'timecreated',
        'lasupdated'
    ];

    $headers = [
        get_string('status', 'tool_tutor_follow'),
        get_string('authorid', 'tool_tutor_follow'),
        get_string('title', 'tool_tutor_follow'),
        get_string('description', 'tool_tutor_follow'),
        get_string('cc_email', 'tool_tutor_follow'),
        get_string('cco_email', 'tool_tutor_follow'),
        get_string('timecreated', 'tool_tutor_follow'),
        get_string('lasupdated', 'tool_tutor_follow')
    ];

    $table->define_columns($columns);
    $table->define_headers($headers);

    $table->sortable(true, 'timecreated', SORT_DESC);
    $table->collapsible(true);
    $table->set_attribute('class', 'generaltable generalbox');

    $table->no_sorting('description');
    $table->setup();

    $records = array_values($DB->get_records('tool_tutor_follow_report'));
    $sortcols = $table->get_sort_columns();

    if (!empty($sortcols)) {
        reset($sortcols);
        $primarycol = key($sortcols);
        $direction = current($sortcols);
        if (in_array($primarycol, $columns)) {
            usort($records, function ($a, $b) use ($primarycol, $direction) {
                $valA = isset($a->{$primarycol}) ? $a->{$primarycol} : null;
                $valB = isset($b->{$primarycol}) ? $b->{$primarycol} : null;
                if (is_numeric($valA) && is_numeric($valB)) {
                    $cmp = ($valA < $valB) ? -1 : (($valA > $valB) ? 1 : 0);
                } else {
                    $cmp = strcasecmp((string)$valA, (string)$valB);
                    if ($cmp < 0) {
                        $cmp = -1;
                    } elseif ($cmp > 0) {
                        $cmp = 1;
                    } else {
                        $cmp = 0;
                    }
                }

                if ($direction === SORT_ASC) {
                    return $cmp;
                } else {
                    return -$cmp;
                }
            });
        }
    }

    foreach ($records as $record) {
        $timecreated = !empty($record->timecreated) ? userdate($record->timecreated) : '-';
        $lasupdated = !empty($record->lasupdated) ? userdate($record->lasupdated) : '-';

        $record->cc_email = !empty($record->cc_email)
            ? tool_tutor_follow_get_listusers($record->cc_email)
            : "";

        $record->cco_email = !empty($record->cco_email)
            ? tool_tutor_follow_get_listusers($record->cco_email)
            : "";

        $url = new moodle_url('user/profile.php', ['id' => $record->authorid]);
        $row = [
            $record->status,
            "<a href='{$url}' target='_blank'>" . fullname($DB->get_record('user', ['id' => $record->authorid])) . "</a>",
            $record->title,
            shorten_text($record->description, 120),
            $record->cc_email,
            $record->cco_email,
            $timecreated,
            $lasupdated
        ];

        $table->add_data($row);
    }
    echo $OUTPUT->render_from_template('tool_tutor_follow/option3/table', []);
    $table->finish_output();
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
    return implode(', ',
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
    global $OUTPUT, $DB;

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

    echo html_writer::tag("p",
        get_string('lastupdate', 'tool_tutor_follow') . ": <b class='text-danger'>"
        . userdate($info->timemodified, get_string('strftimedaydatetime', 'langconfig')) . "</b>"
    );


    $info->course->url_course = new moodle_url('/course/view.php', ['id' => $info->course->id]);

    echo "<a class='h3 text-primary' href='" . $info->course->url_course . "' target='_blank'>" . $info->course->shortname . " - "
        . $info->course->fullname . "</a>";


    $chart = new chart_bar();
    $chart->set_title(get_string('generalfeatures', 'tool_tutor_follow'));
    $chart->set_horizontal(true);

    $series = new chart_series($info->course->shortname . " - "
        . $info->course->fullname,
        [$info->course->total_assign, $info->course->total_forum, $info->course->students,
            $info->course->total_assignments, $info->course->total_posts,
            $info->course->total_grades, $info->course->total_pending_grades]);

    $chart->add_series($series);

    $chart->set_labels([
        get_string('assignments', 'tool_tutor_follow'),
        get_string('forums', 'tool_tutor_follow'),
        get_string('students', 'tool_tutor_follow'),
        get_string('submissions', 'tool_tutor_follow'),
        get_string('contributions', 'tool_tutor_follow'),
        get_string('graded', 'tool_tutor_follow'),
        get_string('pendinggrading', 'tool_tutor_follow')
    ]);


    echo "<div class='w-100'> ";
    echo $OUTPUT->render($chart);
    echo "</div > ";

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

    echo "<div class='container'>";
    echo "
<div class='row bg-success text-white text-center font-weight-bold'>
    <div class='text-center col'>" . get_string('dedicationtimes', 'tool_tutor_follow') . "</div>
</div>
<div class='row bg-primary text-white text-center font-weight-bold'>
    <div class='col'>" . get_string('name', 'tool_tutor_follow') . "</div>
    <div class='col'>" . get_string('startdate', 'tool_tutor_follow') . "</div>
    <div class='col'>" . get_string('dedicationminutes', 'tool_tutor_follow') . "</div>
</div>";


    $chart = new chart_bar();
    $chart->set_title(get_string('dedicationtimes', 'tool_tutor_follow'));
    $chart->set_horizontal(true);

    foreach ($teachers as $teacher) {
        if ($endtime) {
            $dedication_manager = new dedication_time($info->course, '0', $endtime);
        } else {
            $dedication_manager = new dedication_time($info->course, '0', time());
        }
        $accestime = $dedication_manager->get_user_dedication($teacher, false);

        $count_access = 0;
        $minutes = 0;
        if ($accestime) {
            foreach ($accestime as $access) {
                $count_access++;
                echo "<div class='row'>
<div class='col border'><a href='" . $teacher->perfil_url . "'  target='_blank'>" . $teacher->firstname . " " . $teacher->lastname . "</a></div>
<div class='col border'>" . userdate($access->start_date) . "</div>
<div class='col border'>" . round($access->dedicationtime / 60, 1) . "</div>
</div>";
                $minutes += round($access->dedicationtime / 60, 1);
            }
        } else {
            $never = get_string('never', 'tool_tutor_follow');;
            echo "<div class='row'>
<div class='col border'><a href='" . $teacher->perfil_url . "'  target='_blank'>" . $teacher->firstname . " " . $teacher->lastname . "</a></div>
<div class='col border'>" . $never . "</div>
<div class='col border'> 0 </div>
</div>";
        }
        $promedio = ($count_access != 0) ? (round($minutes / $count_access, 1)) : 0;
        $series = new chart_series($teacher->firstname . " " . $teacher->lastname,
            [$count_access, $minutes, $promedio]);

        $chart->add_series($series);
    }

    $chart->set_labels([
        get_string('accesses', 'tool_tutor_follow'),
        get_string('dedicationminutes', 'tool_tutor_follow'),
        get_string('average_dedication', 'tool_tutor_follow')
    ]);
    echo "<hr> ";
    echo $OUTPUT->render($chart);

    $info->number_grades_string = get_string('gradecount', 'tool_tutor_follow');

    echo "</div>";
    echo $OUTPUT->render_from_template('tool_tutor_follow/details/course', $info);
}