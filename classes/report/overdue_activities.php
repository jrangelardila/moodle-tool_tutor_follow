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


class overdue_activities extends report_base
{

    /**
     * Execute report
     *
     * @param $categories
     * @param $roles
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function execute($categories, $roles)
    {
        global $DB;

        $days = time() - (get_config('tool_tutor_follow', 'days_limit_grading') * DAYSECS);

        list($category_sql, $category_params) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
        list($role_sql, $role_params) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'rol');

        $cc_config = get_config('tool_tutor_follow', 'cc_email_default');
        $cc_default = $cc_config ? implode(',', json_decode($cc_config)) : '';

        $data = [];
        $records = [];

        $params = array_merge($category_params, $role_params);
        $params['daysago1'] = $days;
        $params['daysago2'] = $days;

        mtrace('Execute for assigns...');

        $sql = "
SELECT
    CONCAT(a.id, '_', tutor.id) AS uniqueid,
    a.id                AS assignid,
    a.name              AS assignname,
    c.id                AS courseid,
    c.fullname          AS coursename,
    c.shortname,
    c.summary,
    cs.id               AS sectionid,
    cs.name             AS sectionname,
    cs.section          AS sectionnumber,
    cm.id               AS moduleid,
    tutor.id            AS tutorid,
    tutor.firstname,
    tutor.lastname,
    tutor.email,
    tutor.idnumber,
    CASE 
        WHEN a.cutoffdate > 0 THEN a.cutoffdate 
        ELSE a.duedate 
    END AS limitdate
FROM {assign} a
JOIN {course} c              ON c.id = a.course
JOIN {course_modules} cm     ON cm.course = c.id AND cm.instance = a.id
JOIN {modules} m             ON m.id = cm.module AND m.name = 'assign'
JOIN {course_sections} cs    ON cs.id = cm.section
JOIN {context} ctx           ON ctx.instanceid = c.id AND ctx.contextlevel = 50
JOIN {role_assignments} ra   ON ra.contextid = ctx.id
JOIN {role} r                ON r.id = ra.roleid
JOIN {user} tutor            ON tutor.id = ra.userid
WHERE c.category $category_sql
  AND r.shortname $role_sql
  AND tutor.deleted = 0
  AND tutor.suspended = 0
  AND (
        (a.cutoffdate > 0 AND a.cutoffdate <= :daysago1)
     OR (a.cutoffdate = 0 AND a.duedate > 0 AND a.duedate <= :daysago2)
  )
  AND EXISTS (
        SELECT 1
        FROM {user_enrolments} ue
        JOIN {enrol} e ON e.id = ue.enrolid
        WHERE ue.userid = tutor.id
          AND e.courseid = c.id
          AND ue.status = 0
          AND e.status = 0
  )
ORDER BY c.fullname, cs.section, a.name
";

        $activities = $DB->get_records_sql($sql, $params);
        
        foreach ($activities as $row) {

            $submitted = $DB->count_records_sql("
        SELECT COUNT(DISTINCT s.id)
        FROM {assign_submission} s
        JOIN {user} u ON u.id = s.userid
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
        WHERE s.assignment = :assignid
          AND s.status = 'submitted'
          AND s.latest = 1
          AND u.deleted = 0
          AND u.suspended = 0
          AND ue.status = 0
          AND e.status = 0
    ", [
                'assignid' => $row->assignid,
                'courseid' => $row->courseid,
            ]);

            $graded = $DB->count_records_sql("
        SELECT COUNT(DISTINCT s.id)
        FROM {assign_submission} s
        JOIN {assign_grades} g 
             ON g.assignment = s.assignment
            AND g.userid = s.userid
            AND g.attemptnumber = s.attemptnumber
        JOIN {user} u ON u.id = s.userid
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
        WHERE s.assignment = :assignid
          AND s.status = 'submitted'
          AND s.latest = 1
          AND u.deleted = 0
          AND u.suspended = 0
          AND ue.status = 0
          AND e.status = 0
    ", [
                'assignid' => $row->assignid,
                'courseid' => $row->courseid,
            ]);

            if ($submitted <= $graded) {
                continue;
            }

            $pending = $submitted - $graded;

            mtrace("Teacher: {$row->firstname} {$row->lastname} | Assign: {$row->assignname} | Submitted: {$submitted} | Graded: {$graded} | Pending: {$pending}");

            $class = new \stdClass();
            $class->firstname = $row->firstname;
            $class->lastname = $row->lastname;
            $class->idnumber = $row->idnumber;
            $class->email = $row->email;
            $class->coursename = $row->coursename;
            $class->summary = strip_tags($row->summary);
            $class->shortname = $row->shortname;
            $class->section = $row->sectionname;
            $class->type = 'assign';
            $class->activity = $row->assignname;
            $class->limitdate = $row->limitdate;
            $class->num = $pending;
            $class->execution = time();
            $class->limitdatestring = userdate($row->limitdate, get_string('strftimedatetimeshort', 'langconfig'));
            $class->url = (new \moodle_url('/mod/assign/view.php', ['id' => $row->moduleid]))->out(false);

            $data[] = $class;

            $record = new \stdClass();
            $record->status = 0;
            $record->authorid = $row->tutorid;
            $record->title = get_string('title_report_overdue_activities', 'tool_tutor_follow');
            $record->description = get_string('overdue_activities_desc', 'tool_tutor_follow', $class);
            $record->cc_email = $cc_default;
            $record->timecreated = time();
            $record->lasupdated = time();

            $records[] = $record;
        }

        mtrace('Execute for forums (simplified version)...');

        $params = array_merge($category_params, $role_params);
        $params['daysago'] = $days;

        $sql = "
SELECT
    CONCAT(f.id, '_', tutor.id) AS uniqueid,
    f.id             AS forumid,
    f.name           AS forumname,
    f.duedate        AS limitdate,
    c.id             AS courseid,
    c.fullname       AS coursename,
    c.shortname,
    c.summary,
    cs.id            AS sectionid,
    cs.name          AS sectionname,
    cs.section       AS sectionnumber,
    cm.id            AS moduleid,
    tutor.id         AS tutorid,
    tutor.firstname,
    tutor.lastname,
    tutor.email,
    tutor.idnumber
FROM {forum} f
JOIN {course} c              ON c.id = f.course
JOIN {course_modules} cm     ON cm.course = c.id AND cm.instance = f.id
JOIN {modules} m             ON m.id = cm.module AND m.name = 'forum'
JOIN {course_sections} cs    ON cs.id = cm.section
JOIN {context} ctx           ON ctx.instanceid = c.id AND ctx.contextlevel = 50
JOIN {role_assignments} ra   ON ra.contextid = ctx.id
JOIN {role} r                ON r.id = ra.roleid
JOIN {user} tutor            ON tutor.id = ra.userid
WHERE c.category $category_sql
  AND r.shortname $role_sql
  AND tutor.deleted = 0
  AND tutor.suspended = 0
  AND f.assessed > 0
  AND f.duedate > 0
  AND f.duedate <= :daysago
  AND EXISTS (
        SELECT 1
        FROM {user_enrolments} ue
        JOIN {enrol} e ON e.id = ue.enrolid
        WHERE ue.userid = tutor.id
          AND e.courseid = c.id
          AND ue.status = 0
          AND e.status = 0
  )
ORDER BY c.fullname, cs.section, f.name
";

        $forums = $DB->get_records_sql($sql, $params);

        foreach ($forums as $forum) {
            $total_posts = $DB->count_records_sql("
        SELECT COUNT(DISTINCT p.userid)
        FROM {forum_discussions} fd
        JOIN {forum_posts} p ON p.discussion = fd.id AND p.parent = 0
        JOIN {user} u ON u.id = p.userid
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
        JOIN {context} ctx ON ctx.instanceid = :courseid2 AND ctx.contextlevel = 50
        JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ctx.id
        JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'student'
        WHERE fd.forum = :forumid
          AND u.deleted = 0
          AND u.suspended = 0
          AND ue.status = 0
          AND e.status = 0
    ", [
                'forumid' => $forum->forumid,
                'courseid' => $forum->courseid,
                'courseid2' => $forum->courseid,
            ]);

            $total_graded = $DB->count_records_sql("
        SELECT COUNT(DISTINCT gg.userid)
        FROM {grade_items} gi
        JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL
        JOIN {user} u ON u.id = gg.userid
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
        JOIN {context} ctx ON ctx.instanceid = :courseid2 AND ctx.contextlevel = 50
        JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ctx.id
        JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'student'
        WHERE gi.itemmodule = 'forum'
          AND gi.iteminstance = :forumid
          AND gi.courseid = :courseid3
          AND u.deleted = 0
          AND u.suspended = 0
          AND ue.status = 0
          AND e.status = 0
    ", [
                'forumid' => $forum->forumid,
                'courseid' => $forum->courseid,
                'courseid2' => $forum->courseid,
                'courseid3' => $forum->courseid,
            ]);

            $pending = $total_posts - $total_graded;

            if ($pending <= 0) {
                continue;
            }

            mtrace(
                "Teacher: {$forum->firstname} {$forum->lastname} | " .
                "Forum: {$forum->forumname} | " .
                "Students with post: {$total_posts} | " .
                "Graded: {$total_graded} | " .
                "Pending: {$pending}"
            );

            $class = new \stdClass();
            $class->firstname = $forum->firstname;
            $class->lastname = $forum->lastname;
            $class->idnumber = $forum->idnumber;
            $class->email = $forum->email;
            $class->coursename = $forum->coursename;
            $class->summary = strip_tags($forum->summary);
            $class->shortname = $forum->shortname;
            $class->section = $forum->sectionname;
            $class->type = 'forum';
            $class->activity = $forum->forumname;
            $class->limitdate = $forum->limitdate;
            $class->num = $pending;
            $class->execution = time();
            $class->limitdatestring = userdate(
                $forum->limitdate,
                get_string('strftimedatetimeshort', 'langconfig')
            );
            $class->url = (new moodle_url(
                '/mod/forum/view.php',
                ['id' => $forum->moduleid]
            ))->out(false);

            $data[] = $class;

            $record = new \stdClass();
            $record->status = 0;
            $record->authorid = $forum->tutorid;
            $record->title = get_string('title_report_overdue_activities', 'tool_tutor_follow');
            $record->description = get_string('overdue_activities_desc', 'tool_tutor_follow', $class);
            $record->cc_email = $cc_default;
            $record->timecreated = time();
            $record->lasupdated = time();

            $records[] = $record;
        }

        if (!empty($records)) {
            $DB->insert_records('tool_tutor_follow_report', $records);
        }

        // Save data in json
        tool_tutor_follow_save_data_json(
            json_encode($data),
            'report_overdue_activities',
            'reports'
        );
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
            get_string('firstname', 'moodle'),
            get_string('lastname', 'moodle'),
            get_string('email', 'moodle'),
            get_string('idnumber', 'moodle'),
            get_string('coursename', 'tool_tutor_follow'),
            get_string('course_shortname', 'tool_tutor_follow'),
            get_string('course_summary', 'tool_tutor_follow'),
            get_string('type', 'tool_tutor_follow'),
            get_string('nameactivity', 'tool_tutor_follow'),
            get_string('limitdate', 'tool_tutor_follow'),
            get_string('count', 'tool_tutor_follow'),
            get_string('url', 'moodle')
        ];

        $exportdata = [];
        foreach ($data as $row) {
            $exportdata[] = [
                $row->firstname,
                $row->lastname,
                $row->email,
                $row->idnumber,
                $row->coursename,
                $row->shortname,
                strip_tags($row->summary),
                $row->type,
                $row->activity,
                $row->limitdatestring,
                $row->num,
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
