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

namespace tool_tutor_follow\report;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/tutor_follow/lib.php');


class list_overdue_activities extends report_base
{

    /**
     * Execute task
     *
     * @param $categories
     * @param $roles
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function execute($categories, $roles)
    {
        global $DB;

        $days = time() - (get_config('tool_tutor_follow', 'days_limit_grading') * 24 * 60 * 60);

        list($category_sql, $category_params) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
        list($role_sql, $role_params) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'rol');

        $cc_config = get_config('tool_tutor_follow', 'cc_email_default');
        $cc_default = $cc_config ? implode(',', json_decode($cc_config)) : '';

        $data = [];
        $activities_records = [];
        mtrace("Execute for assigns...");
        $this->process_assignments(
            $category_sql, $role_sql, $category_params, $role_params,
            $days, $cc_default, $data, $activities_records
        );

        mtrace("Execute for forums...");
        $this->process_forums(
            $category_sql, $role_sql, $category_params, $role_params,
            $days, $cc_default, $data, $activities_records
        );
        if (!empty($activities_records)) {
            $DB->insert_records('tool_tutor_follow_report', $activities_records);
        }

        tool_tutor_follow_save_data_json(
            json_encode($data),
            'report_list_overdue_activities',
            'reports'
        );
    }

    /**
     * Process assigns
     *
     * @param $category_sql
     * @param $role_sql
     * @param $category_params
     * @param $role_params
     * @param $days
     * @param $cc_default
     * @param $data
     * @param $activities_records
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function process_assignments($category_sql, $role_sql, $category_params, $role_params, $days, $cc_default, &$data, &$activities_records)
    {
        $ungraded_submissions = $this->get_ungraded_assign_submissions($category_sql, $category_params, $days);

        $course_ids = $this->extract_unique_course_ids($ungraded_submissions);
        $teachers_by_course = $this->get_teachers_by_courses($course_ids, $role_sql, $role_params);

        $combined_data = $this->combine_submissions_with_teachers($ungraded_submissions, $teachers_by_course);
        $activities_submissions = $this->process_assign_results($combined_data, $data);
        $this->create_assign_records($activities_submissions, $cc_default, $activities_records);
    }

    /**
     * Get subimissions without grades
     *
     * @param $category_sql
     * @param $category_params
     * @param $days
     * @return array
     * @throws \dml_exception
     */
    private function get_ungraded_assign_submissions($category_sql, $category_params, $days)
    {
        global $DB;

        $params = $category_params;
        $params['now'] = $days;
        $params['nowend'] = $days;

        $sql = "
            SELECT 
                s.id AS submission_id,
                s.userid AS student_userid,
                s.timecreated AS submission_date,
                s.attemptnumber,
                
                a.id AS assign_id,
                a.name AS assign_name,
                a.cutoffdate AS limitdate,
                a.duedate,
                
                c.id AS course_id,
                c.fullname AS course_name,
                c.shortname AS course_shortname,
                c.summary AS course_summary,
                
                cm.id AS module_id,
                
                cs.name AS section_name,
                cs.section AS section_number,
                
                u.id AS student_id,
                u.idnumber AS student_idnumber,
                u.firstname AS student_firstname,
                u.lastname AS student_lastname,
                u.email AS student_email
                
            FROM {assign_submission} s
            
            JOIN {assign} a ON a.id = s.assignment
            JOIN {course} c ON c.id = a.course
            
            JOIN {course_modules} cm 
                ON cm.course = c.id 
                AND cm.instance = a.id
            
            JOIN {modules} m 
                ON m.id = cm.module 
                AND m.name = 'assign'
            
            JOIN {course_sections} cs ON cs.id = cm.section
            
            JOIN {user} u 
                ON u.id = s.userid 
                AND u.deleted = 0 
                AND u.suspended = 0
            
            JOIN {enrol} e 
                ON e.courseid = c.id 
                AND e.status = 0
            
            JOIN {user_enrolments} ue 
                ON ue.enrolid = e.id 
                AND ue.userid = u.id 
                AND ue.status = 0
            
            LEFT JOIN {assign_grades} g 
                ON g.assignment = a.id 
                AND g.userid = s.userid 
                AND g.attemptnumber = s.attemptnumber
            
            WHERE c.category {$category_sql}
                AND s.status = 'submitted'
                AND s.latest = 1
                AND g.id IS NULL
                AND (
                    (a.cutoffdate > 0 AND a.cutoffdate <= :now)
                    OR (a.cutoffdate = 0 AND a.duedate > 0 AND a.duedate <= :nowend)
                )
            
            ORDER BY c.fullname, cs.section, a.name, u.lastname, u.firstname
        ";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Process assign for results
     *
     * @param $combined_data
     * @param $data
     * @return array
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function process_assign_results($combined_data, &$data)
    {
        $activities_submissions = [];

        foreach ($combined_data as $row) {
            mtrace("Teacher: {$row->teacher_firstname} {$row->teacher_lastname} | Student: {$row->student_firstname} {$row->student_lastname} | Assign: {$row->assign_name}");

            $class = $this->create_assign_data_object($row);
            $data[] = $class;

            $activity_key = $row->teacher_userid . '_' . $row->course_id . '_' . $row->assign_id;

            if (!isset($activities_submissions[$activity_key])) {
                $activities_submissions[$activity_key] = [
                    'teacher_userid' => $row->teacher_userid,
                    'teacher_firstname' => $row->teacher_firstname,
                    'teacher_lastname' => $row->teacher_lastname,
                    'course' => $row->course_name,
                    'shortname' => $row->course_shortname,
                    'summary' => strip_tags($row->course_summary),
                    'activity' => $row->assign_name,
                    'limitdatestring' => $class->limitdatestring,
                    'submissions' => []
                ];
            }

            $activities_submissions[$activity_key]['submissions'][] = [
                'student_firstname' => $row->student_firstname,
                'student_lastname' => $row->student_lastname,
                'student_idnumber' => $row->student_idnumber,
                'student_email' => $row->student_email,
                'submission_datestring' => $class->submission_datestring,
                'url' => $class->url
            ];
        }

        return $activities_submissions;
    }

    /**
     * Create data stdclass for assign
     *
     * @param $row
     * @return \stdClass
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function create_assign_data_object($row)
    {
        $class = new \stdClass();

        $class->teacher_firstname = $row->teacher_firstname;
        $class->teacher_lastname = $row->teacher_lastname;
        $class->teacher_idnumber = $row->teacher_idnumber;
        $class->teacher_email = $row->teacher_email;
        $class->teacher_userid = $row->teacher_userid;

        $class->student_firstname = $row->student_firstname;
        $class->student_lastname = $row->student_lastname;
        $class->student_idnumber = $row->student_idnumber;
        $class->student_email = $row->student_email;
        $class->student_userid = $row->student_id;

        $class->coursename = $row->course_name;
        $class->summary = strip_tags($row->course_summary);
        $class->shortname = $row->course_shortname;
        $class->section = $row->section_name;

        $class->type = 'assign';
        $class->activity = $row->assign_name;
        $class->activity_id = $row->assign_id;
        $class->limitdate = $row->limitdate;
        $class->duedate = $row->duedate;

        $class->submission_date = $row->submission_date;
        $class->submission_id = $row->submission_id;
        $class->execution = time();

        $class->limitdatestring = $this->format_limit_date($row->limitdate, $row->duedate);
        $class->submission_datestring = userdate($row->submission_date, get_string('strftimedatetimeshort', 'langconfig'));

        $url_object = new moodle_url(
            '/mod/assign/view.php',
            [
                'id' => $row->module_id,
                'action' => 'grade',
                'userid' => $row->student_id,
            ]
        );

        $class->url = $url_object->out(false);

        return $class;
    }

    /**
     * Create assign records
     *
     * @param $activities_submissions
     * @param $cc_default
     * @param $activities_records
     * @return void
     * @throws \coding_exception
     */
    private function create_assign_records($activities_submissions, $cc_default, &$activities_records)
    {
        foreach ($activities_submissions as $activity_data) {
            $table_html = $this->generate_submissions_table($activity_data['submissions']);

            $record = new \stdClass();
            $record->status = 0;
            $record->authorid = $activity_data['teacher_userid'];
            $record->title = get_string('title_report_list_overdue_activities', 'tool_tutor_follow');
            $record->description = $this->build_description($activity_data, $table_html, 'submissions');
            $record->cc_email = $cc_default;
            $record->timecreated = time();
            $record->lasupdated = time();

            $activities_records[] = $record;
        }
    }

    /**
     * Process forums
     *
     * @param $category_sql
     * @param $role_sql
     * @param $category_params
     * @param $role_params
     * @param $days
     * @param $cc_default
     * @param $data
     * @param $activities_records
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function process_forums($category_sql, $role_sql, $category_params, $role_params, $days, $cc_default, &$data, &$activities_records)
    {
        $forums = $this->get_gradable_forums($category_sql, $category_params, $days);

        $forum_ids = array_column((array)$forums, 'forum_id');
        $ungraded_posts = $this->get_ungraded_first_posts($forum_ids, $forums);
        $course_ids = $this->extract_unique_course_ids_from_forums($forums);
        $teachers_by_course = $this->get_teachers_by_courses($course_ids, $role_sql, $role_params);
        $combined_data = $this->combine_forum_posts_with_teachers($ungraded_posts, $teachers_by_course, $forums);
        $activities_forum_submissions = $this->process_forum_results($combined_data, $data);
        $this->create_forum_records($activities_forum_submissions, $cc_default, $activities_records);
    }

    /**
     * Get forums with days limit
     *
     * @param $category_sql
     * @param $category_params
     * @param $days
     * @return array
     * @throws \dml_exception
     */
    private function get_gradable_forums($category_sql, $category_params, $days)
    {
        global $DB;

        $params = $category_params;
        $params['daysago'] = $days;

        $sql = "
            SELECT 
                f.id AS forum_id,
                f.name AS forum_name,
                f.duedate AS limitdate,
                f.assessed,
                
                c.id AS course_id,
                c.fullname AS course_name,
                c.shortname AS course_shortname,
                c.summary AS course_summary,
                
                cm.id AS module_id,
                
                cs.name AS section_name,
                cs.section AS section_number
                
            FROM {forum} f
            
            JOIN {course} c ON c.id = f.course
            
            JOIN {course_modules} cm 
                ON cm.course = c.id 
                AND cm.instance = f.id
            
            JOIN {modules} m 
                ON m.id = cm.module 
                AND m.name = 'forum'
            
            JOIN {course_sections} cs ON cs.id = cm.section
            
            WHERE c.category {$category_sql}
                AND f.assessed > 0
                AND f.duedate > 0
                AND f.duedate <= :daysago
            
            ORDER BY c.fullname, cs.section, f.name
        ";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get first post in each forums
     *
     * @param $forum_ids
     * @param $forums
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function get_ungraded_first_posts($forum_ids, $forums)
    {
        global $DB;

        if (empty($forum_ids)) {
            return [];
        }

        list($forum_sql, $forum_params) = $DB->get_in_or_equal($forum_ids, SQL_PARAMS_NAMED, 'forum');

        $module_ids = array_column((array)$forums, 'module_id', 'forum_id');

        $sql = "
            SELECT 
                p.id AS post_id,
                p.discussion AS discussion_id,
                p.userid AS student_userid,
                p.created AS submission_date,
                
                fd.forum AS forum_id,
                fd.name AS discussion_name,
                
                u.id AS student_id,
                u.idnumber AS student_idnumber,
                u.firstname AS student_firstname,
                u.lastname AS student_lastname,
                u.email AS student_email,
                
                f.course AS course_id
                
            FROM {forum_discussions} fd
            
            JOIN {forum} f ON f.id = fd.forum
            
            JOIN {forum_posts} p 
                ON p.discussion = fd.id 
                AND p.id = (
                    SELECT MIN(p2.id) 
                    FROM {forum_posts} p2 
                    WHERE p2.discussion = fd.id 
                    AND p2.parent = 0
                )
            
            JOIN {user} u 
                ON u.id = p.userid 
                AND u.deleted = 0 
                AND u.suspended = 0
            
            JOIN {enrol} e 
                ON e.courseid = f.course 
                AND e.status = 0
            
            JOIN {user_enrolments} ue 
                ON ue.enrolid = e.id 
                AND ue.userid = u.id 
                AND ue.status = 0
            
            JOIN {course_modules} cm 
                ON cm.instance = f.id 
                AND cm.course = f.course
            
            JOIN {modules} m 
                ON m.id = cm.module 
                AND m.name = 'forum'
            
            JOIN {context} ctx_module 
                ON ctx_module.contextlevel = 70 
                AND ctx_module.instanceid = cm.id
            
            -- Sin calificación (rating)
            LEFT JOIN {rating} rt 
                ON rt.contextid = ctx_module.id 
                AND rt.itemid = p.id
            
            WHERE fd.forum {$forum_sql}
                AND rt.id IS NULL
            
            ORDER BY fd.forum, u.lastname, u.firstname
        ";

        return $DB->get_records_sql($sql, $forum_params);
    }

    /**
     * Combine forums with teachers
     *
     * @param $ungraded_posts
     * @param $teachers_by_course
     * @param $forums
     * @return array
     */
    private function combine_forum_posts_with_teachers($ungraded_posts, $teachers_by_course, $forums)
    {
        $combined = [];

        $forums_indexed = [];
        foreach ($forums as $forum) {
            $forums_indexed[$forum->forum_id] = $forum;
        }

        foreach ($ungraded_posts as $post) {
            $course_id = $post->course_id;

            if (!isset($teachers_by_course[$course_id])) {
                continue;
            }

            $forum_data = $forums_indexed[$post->forum_id] ?? null;
            if (!$forum_data) {
                continue;
            }

            foreach ($teachers_by_course[$course_id] as $teacher) {
                $combined_row = new \stdClass();

                $combined_row->teacher_userid = $teacher->teacher_userid;
                $combined_row->teacher_idnumber = $teacher->teacher_idnumber;
                $combined_row->teacher_firstname = $teacher->teacher_firstname;
                $combined_row->teacher_lastname = $teacher->teacher_lastname;
                $combined_row->teacher_email = $teacher->teacher_email;

                $combined_row->student_id = $post->student_id;
                $combined_row->student_idnumber = $post->student_idnumber;
                $combined_row->student_firstname = $post->student_firstname;
                $combined_row->student_lastname = $post->student_lastname;
                $combined_row->student_email = $post->student_email;

                $combined_row->course_id = $forum_data->course_id;
                $combined_row->course_name = $forum_data->course_name;
                $combined_row->course_shortname = $forum_data->course_shortname;
                $combined_row->course_summary = $forum_data->course_summary;
                $combined_row->section_name = $forum_data->section_name;

                $combined_row->forum_id = $post->forum_id;
                $combined_row->forum_name = $forum_data->forum_name;
                $combined_row->limitdate = $forum_data->limitdate;
                $combined_row->module_id = $forum_data->module_id;

                $combined_row->post_id = $post->post_id;
                $combined_row->discussion_id = $post->discussion_id;
                $combined_row->submission_date = $post->submission_date;

                $combined[] = $combined_row;
            }
        }

        return $combined;
    }


    /**
     * Action with results for forums
     *
     * @param $combined_data
     * @param $data
     * @return array
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function process_forum_results($combined_data, &$data)
    {
        $activities_forum_submissions = [];

        foreach ($combined_data as $row) {
            mtrace("Teacher: {$row->teacher_firstname} {$row->teacher_lastname} | Student: {$row->student_firstname} {$row->student_lastname} | Forum: {$row->forum_name}");

            $class = $this->create_forum_data_object($row);
            $data[] = $class;

            $activity_key = $row->teacher_userid . '_' . $row->course_id . '_' . $row->forum_id;

            if (!isset($activities_forum_submissions[$activity_key])) {
                $activities_forum_submissions[$activity_key] = [
                    'teacher_userid' => $row->teacher_userid,
                    'teacher_firstname' => $row->teacher_firstname,
                    'teacher_lastname' => $row->teacher_lastname,
                    'course' => $row->course_name,
                    'shortname' => $row->course_shortname,
                    'summary' => strip_tags($row->course_summary),
                    'activity' => $row->forum_name,
                    'limitdatestring' => $class->limitdatestring,
                    'submissions' => []
                ];
            }

            $activities_forum_submissions[$activity_key]['submissions'][] = [
                'student_firstname' => $row->student_firstname,
                'student_lastname' => $row->student_lastname,
                'student_idnumber' => $row->student_idnumber,
                'student_email' => $row->student_email,
                'submission_datestring' => $class->submission_datestring,
                'url' => $class->url
            ];
        }

        return $activities_forum_submissions;
    }

    /**
     * Create data forums stdclass
     *
     * @param $row
     * @return \stdClass
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function create_forum_data_object($row)
    {
        $class = new \stdClass();

        $class->teacher_firstname = $row->teacher_firstname;
        $class->teacher_lastname = $row->teacher_lastname;
        $class->teacher_idnumber = $row->teacher_idnumber;
        $class->teacher_email = $row->teacher_email;
        $class->teacher_userid = $row->teacher_userid;

        $class->student_firstname = $row->student_firstname;
        $class->student_lastname = $row->student_lastname;
        $class->student_idnumber = $row->student_idnumber;
        $class->student_email = $row->student_email;
        $class->student_userid = $row->student_id;

        $class->coursename = $row->course_name;
        $class->summary = strip_tags($row->course_summary);
        $class->shortname = $row->course_shortname;
        $class->section = $row->section_name;

        $class->type = 'forum';
        $class->activity = $row->forum_name;
        $class->activity_id = $row->forum_id;
        $class->limitdate = $row->limitdate;

        $class->submission_date = $row->submission_date;
        $class->post_id = $row->post_id;
        $class->execution = time();

        $class->limitdatestring = userdate($row->limitdate, get_string('strftimedatetimeshort', 'langconfig'));
        $class->submission_datestring = userdate($row->submission_date, get_string('strftimedatetimeshort', 'langconfig'));

        $url_object = new moodle_url('/mod/forum/discuss.php', [
            'd' => $row->discussion_id,
            'p' => $row->post_id
        ]);
        $class->url = $url_object->out(false);

        return $class;
    }

    /**
     * Create records forums
     *
     * @param $activities_forum_submissions
     * @param $cc_default
     * @param $activities_records
     * @return void
     * @throws \coding_exception
     */
    private function create_forum_records($activities_forum_submissions, $cc_default, &$activities_records)
    {
        foreach ($activities_forum_submissions as $activity_data) {
            $table_html = $this->generate_submissions_table($activity_data['submissions']);

            $record = new \stdClass();
            $record->status = 0;
            $record->authorid = $activity_data['teacher_userid'];
            $record->title = get_string('title_report_list_overdue_activities', 'tool_tutor_follow');
            $record->description = $this->build_description($activity_data, $table_html, 'posts');
            $record->cc_email = $cc_default;
            $record->timecreated = time();
            $record->lasupdated = time();

            $activities_records[] = $record;
        }
    }

    /**
     * Get teachers in courses
     *
     * @param $course_ids
     * @param $role_sql
     * @param $role_params
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function get_teachers_by_courses($course_ids, $role_sql, $role_params)
    {
        global $DB;

        if (empty($course_ids)) {
            return [];
        }

        list($course_sql, $course_params) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'course');
        $params = array_merge($course_params, $role_params);

        $sql = "
            SELECT 
                CONCAT(c.id, '_', t.id) AS unique_key,
                c.id AS course_id,
                t.id AS teacher_userid,
                t.idnumber AS teacher_idnumber,
                t.firstname AS teacher_firstname,
                t.lastname AS teacher_lastname,
                t.email AS teacher_email
                
            FROM {course} c
            
            JOIN {context} ctx 
                ON ctx.instanceid = c.id 
                AND ctx.contextlevel = 50
            
            JOIN {role_assignments} ra ON ra.contextid = ctx.id
            
            JOIN {role} r 
                ON r.id = ra.roleid 
                AND r.shortname {$role_sql}
            
            JOIN {user} t 
                ON t.id = ra.userid 
                AND t.deleted = 0 
                AND t.suspended = 0
            
            JOIN {enrol} et 
                ON et.courseid = c.id 
                AND et.status = 0
            
            JOIN {user_enrolments} uet 
                ON uet.enrolid = et.id 
                AND uet.userid = t.id 
                AND uet.status = 0
            
            WHERE c.id {$course_sql}
            
            ORDER BY c.id, t.lastname, t.firstname
        ";

        $results = $DB->get_records_sql($sql, $params);

        $teachers_by_course = [];
        foreach ($results as $row) {
            if (!isset($teachers_by_course[$row->course_id])) {
                $teachers_by_course[$row->course_id] = [];
            }
            $teachers_by_course[$row->course_id][] = $row;
        }

        return $teachers_by_course;
    }

    /**
     * Combine subimissions with teachers
     *
     * @param $submissions
     * @param $teachers_by_course
     * @return array
     */
    private function combine_submissions_with_teachers($submissions, $teachers_by_course)
    {
        $combined = [];

        foreach ($submissions as $submission) {
            $course_id = $submission->course_id;

            if (!isset($teachers_by_course[$course_id])) {
                continue;
            }

            foreach ($teachers_by_course[$course_id] as $teacher) {
                $combined_row = clone $submission;

                $combined_row->teacher_userid = $teacher->teacher_userid;
                $combined_row->teacher_idnumber = $teacher->teacher_idnumber;
                $combined_row->teacher_firstname = $teacher->teacher_firstname;
                $combined_row->teacher_lastname = $teacher->teacher_lastname;
                $combined_row->teacher_email = $teacher->teacher_email;

                $combined[] = $combined_row;
            }
        }

        return $combined;
    }

    /**
     * Extract unique courseids
     *
     * @param $submissions
     * @return array
     */
    private function extract_unique_course_ids($submissions)
    {
        $course_ids = [];
        foreach ($submissions as $submission) {
            $course_ids[$submission->course_id] = $submission->course_id;
        }
        return array_values($course_ids);
    }

    /**
     * Extract unique ids from forums
     *
     * @param $forums
     * @return array
     */
    private function extract_unique_course_ids_from_forums($forums)
    {
        $course_ids = [];
        foreach ($forums as $forum) {
            $course_ids[$forum->course_id] = $forum->course_id;
        }
        return array_values($course_ids);
    }


    /**
     * format for dates
     *
     * @param $limitdate
     * @param $duedate
     * @return string
     * @throws \coding_exception
     */
    private function format_limit_date($limitdate, $duedate = 0)
    {
        if ($limitdate > 0) {
            return userdate($limitdate, get_string('strftimedatetimeshort', 'langconfig'));
        }
        if ($duedate > 0) {
            return userdate($duedate, get_string('strftimedatetimeshort', 'langconfig'));
        }
        return 'N/A';
    }

    /**
     * create description
     *
     * @param $activity_data
     * @param $table_html
     * @param $type
     * @return string
     * @throws \coding_exception
     */
    private function build_description($activity_data, $table_html, $type = 'submissions')
    {
        $pending_string = $type === 'posts'
            ? get_string('desc_pending_posts', 'tool_tutor_follow')
            : get_string('desc_pending_submissions', 'tool_tutor_follow');

        return '<strong>' . get_string('desc_teacher', 'tool_tutor_follow') . ':</strong> ' .
            $activity_data['teacher_firstname'] . ' ' . $activity_data['teacher_lastname'] . '<br>' .
            '<strong>' . get_string('desc_course', 'tool_tutor_follow') . ':</strong> ' .
            $activity_data['course'] . ' (' . $activity_data['shortname'] . ')<br>' .
            '<strong>' . get_string('desc_activity', 'tool_tutor_follow') . ':</strong> ' .
            $activity_data['activity'] . '<br>' .
            '<strong>' . get_string('desc_submission_deadline', 'tool_tutor_follow') . ':</strong> ' .
            $activity_data['limitdatestring'] . '<br>' .
            '<strong>' . $pending_string . ':</strong><br>' . $table_html;
    }

    /**
     * Return table submissions
     *
     * @param $submissions
     * @return string
     * @throws \coding_exception
     */
    private function generate_submissions_table($submissions)
    {
        $html = '<table style="border-collapse: collapse; width: 100%; border: 1px solid #ddd;">';
        $html .= '<thead><tr style="background-color: #f2f2f2;">';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_student', 'tool_tutor_follow') . '</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_idnumber', 'tool_tutor_follow') . '</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_email', 'tool_tutor_follow') . '</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_submission_date', 'tool_tutor_follow') . '</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_url', 'tool_tutor_follow') . '</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ($submissions as $submission) {
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $submission['student_firstname'] . ' ' . $submission['student_lastname'] . '</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $submission['student_idnumber'] . '</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $submission['student_email'] . '</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $submission['submission_datestring'] . '</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;"><a href="' . $submission['url'] . '" target="_blank">' . get_string('table_header_view', 'tool_tutor_follow') . '</a></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Download report
     *
     * @param $shortname
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function download_report($shortname)
    {
        $rawdata = tool_tutor_follow_get_data("report_{$shortname}", 'reports');
        $data = json_decode($rawdata);

        $columns = [
            get_string('teacher_firstname', 'tool_tutor_follow'),
            get_string('teacher_lastname', 'tool_tutor_follow'),
            get_string('teacher_email', 'tool_tutor_follow'),
            get_string('teacher_idnumber', 'tool_tutor_follow'),
            get_string('student_firstname', 'tool_tutor_follow'),
            get_string('student_lastname', 'tool_tutor_follow'),
            get_string('student_email', 'tool_tutor_follow'),
            get_string('student_idnumber', 'tool_tutor_follow'),
            get_string('coursename', 'tool_tutor_follow'),
            get_string('course_shortname', 'tool_tutor_follow'),
            get_string('course_summary', 'tool_tutor_follow'),
            get_string('section', 'tool_tutor_follow'),
            get_string('type', 'tool_tutor_follow'),
            get_string('nameactivity', 'tool_tutor_follow'),
            get_string('limitdate', 'tool_tutor_follow'),
            get_string('submission_date', 'tool_tutor_follow'),
            get_string('url', 'moodle')
        ];

        $exportdata = [];
        foreach ($data as $row) {
            $exportdata[] = [
                $row->teacher_firstname,
                $row->teacher_lastname,
                $row->teacher_email,
                $row->teacher_idnumber,
                $row->student_firstname,
                $row->student_lastname,
                $row->student_email,
                $row->student_idnumber,
                $row->coursename,
                $row->shortname,
                strip_tags($row->summary),
                $row->section,
                $row->type,
                $row->activity,
                $row->limitdatestring,
                isset($row->submission_datestring) ? $row->submission_datestring : 'N/A',
                $row->url,
            ];
        }

        $filename = "reporte_" . $shortname . "_" . date('Ymd');

        \core\dataformat::download_data(
            $filename,
            'excel',
            $columns,
            $exportdata
        );
    }
}

