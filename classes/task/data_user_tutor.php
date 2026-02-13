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

use moodle_page;
use moodle_url;
use stdClass;
use tool_tutor_follow\adhoc\execute_data_course;
use tool_tutor_follow\adhoc\execute_data_user;
use user_picture;

require_once(__DIR__ . "/../../lib.php");
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Traer los usuarios con el rol respectivo, y sus distintos cursos inscritos
 */
class data_user_tutor extends \core\task\scheduled_task
{
    const DATA_TYPE = [
        'USER' => 0,
        'COURSE' => 1.
    ];

    /**
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name()
    {
        return get_string('createdatauser', 'tool_tutor_follow');
    }

    /**
     * @return void
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     * @throws \moodle_exception
     */
    public function execute()
    {
        global $DB;

        mtrace(get_string('createdatauser', 'tool_tutor_follow'));

        $category = json_decode(get_config('tool_tutor_follow', 'categories'));
        $roles = json_decode(get_config('tool_tutor_follow', 'roles'));

        list($category_sql, $category_params) = $DB->get_in_or_equal($category, SQL_PARAMS_NAMED, 'cat');
        list($roles_sql, $roles_params) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'role');
        $params = array_merge($category_params, $roles_params);

        $sql_users = "
        SELECT DISTINCT u.*
          FROM {user} u
          JOIN {role_assignments} ra ON ra.userid = u.id
          JOIN {context} ctx ON ctx.id = ra.contextid
          JOIN {role} r ON r.id = ra.roleid
          JOIN {course} c ON c.id = ctx.instanceid
          JOIN {enrol} e ON e.courseid = c.id
          JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = u.id
         WHERE ue.status = 0
           AND c.category $category_sql
           AND r.shortname $roles_sql";
        $users = $DB->get_records_sql($sql_users, $params);

        mtrace(get_string('count_users', 'tool_tutor_follow') . count($users));

        $sql_user_courses = "
        SELECT DISTINCT u.id AS userid, c.*
          FROM {user} u
          JOIN {role_assignments} ra ON ra.userid = u.id
          JOIN {context} ctx ON ctx.id = ra.contextid
          JOIN {role} r ON r.id = ra.roleid
          JOIN {course} c ON c.id = ctx.instanceid
          JOIN {enrol} e ON e.courseid = c.id
          JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = u.id
         WHERE ue.status = 0
           AND c.category $category_sql
           AND r.shortname $roles_sql";

        $usercourses = $DB->get_records_sql($sql_user_courses, $params);

        $courses_in_user = [];
        foreach ($usercourses as $record) {
            $courses_in_user[$record->userid][] = $record;
        }
        $info = ['users' => []];

        foreach ($users as $user) {
            mtrace(get_string('updated_user', 'tool_tutor_follow') . $user->firstname . " " . $user->lastname);

            $cursos = $courses_in_user[$user->id] ?? [];

            $user->numcursos = count($cursos);
            $user->cursos = [];

            $all_students = 0;
            $activities_for_calification = 0;
            $activities_no_grade = 0;

            foreach ($cursos as $course) {
                $num_students = tool_tutor_follow_get_count_students($course->id);
                mtrace(get_string('course_student_count', 'tool_tutor_follow', [
                    'shortname' => $course->shortname,
                    'count' => $num_students
                ]));
                $course->students = $num_students;

                self::created_adhoc_task($course);

                $acts1 = self::activities_for_calification($course->id);
                $act2 = self::activities_no_grade($course->id);

                $course->activities_to_grade = $acts1;
                $course->activities_no_grade = $act2;

                $all_students += $num_students;
                $activities_for_calification += count($acts1);
                $activities_no_grade += count($act2);

                $user->cursos[] = $course;
            }

            $user->activities_for_calification = $activities_for_calification;
            $user->activities_no_grade = $activities_no_grade;
            $user->all_students = $all_students;

            $picture = new user_picture($user);
            $picture->size = 110;
            $page = new moodle_page();
            $user->picture = $picture->get_url($page)->out();

            $user->perfil_url = (new moodle_url("/user/profile.php", ['id' => $user->id]))->out();
            $user->lastacces_text = userdate($user->lastaccess);

            $info['users'][] = $user;
        }

        mtrace(get_string('save_file', 'tool_tutor_follow'));
        tool_tutor_follow_save_data_json(json_encode($info), 'json_user_data', 'data_user');
    }

    /**
     * Activities for calification
     * @param $courseid
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    static function activities_for_calification($courseid)
    {
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $activities_to_grade = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->modname === 'assign' || $cm->modname === 'forum') {
                $activities_to_grade[] = $cm;
            }
        }

        return $activities_to_grade;
    }

    /**
     * Return activities no calification in its config
     * @param $courseid
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    static function activities_no_grade($courseid)
    {
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $activities_no_grade = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->modname === 'quiz' || $cm->modname === 'h5pactivity') {
                $activities_no_grade[] = $cm;
            }
        }
        return $activities_no_grade;
    }

    /**
     * Created the adhoc for each course
     *
     * @param stdClass $customdata
     * @return void
     */
    static function created_adhoc_task(stdClass $customdata)
    {
        $task = new execute_data_course();
        $task->set_custom_data($customdata);
        \core\task\manager::queue_adhoc_task($task);
    }
}
