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
 * Report of submissions graded without feedback last week.
 *
 * @package   tool_tutor_follow
 * @copyright 2026 jrangelardila <jrangelardila@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tutor_follow\report;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

class graded_without_feedback extends report_base
{
    /**
     * Execute report.
     *
     * @param array $categories Category ids to analyze.
     * @param array $roles Role shortnames considered as teachers.
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function execute($categories, $roles)
    {
        global $DB;

        list($category_sql, $category_params) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
        list($role_sql, $role_params) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'rol');

        $cc_config = get_config('tool_tutor_follow', 'cc_email_default');
        $cc_default = $cc_config ? implode(',', json_decode($cc_config)) : '';

        $data = [];

        mtrace("Execute for assigns...");
        $assignrows = $this->get_assign_rows($category_sql, $role_sql, $category_params, $role_params);

        mtrace("Execute for forums...");
        $forumrows = $this->get_forum_rows($category_sql, $role_sql, $category_params, $role_params);

        $courses = $this->aggregate_by_course($assignrows, $forumrows, $data);

        if (!empty($courses)) {
            $records = $this->build_course_records($courses, $cc_default);
            $DB->insert_records('tool_tutor_follow_report', $records);
        }

        tool_tutor_follow_save_data_json(
            json_encode($data),
            'report_graded_without_feedback',
            'reports'
        );
    }

    /**
     * Fetch assign grades that have no meaningful feedback comment.
     *
     * @param string $category_sql IN/EQUAL clause for course categories.
     * @param string $role_sql IN/EQUAL clause for teacher role shortnames.
     * @param array $category_params Named parameters for $category_sql.
     * @param array $role_params Named parameters for $role_sql.
     * @return array Records keyed by grade id.
     * @throws \dml_exception
     */
    private function get_assign_rows($category_sql, $role_sql, $category_params, $role_params)
    {
        global $DB;

        $params = array_merge($category_params, $role_params);

        $sql = "
            SELECT
                ag.id AS grade_id,
                ag.grade AS grade,
                ag.timemodified AS graded_at,
                ag.grader AS teacher_userid,
                ag.userid AS student_userid,

                a.id AS assign_id,
                a.name AS activity_name,

                c.id AS course_id,
                c.fullname AS course_name,
                c.shortname AS course_shortname,
                c.summary AS course_summary,

                cm.id AS module_id,

                u.idnumber AS student_idnumber,
                u.firstname AS student_firstname,
                u.lastname AS student_lastname,
                u.email AS student_email,

                t.idnumber AS teacher_idnumber,
                t.firstname AS teacher_firstname,
                t.lastname AS teacher_lastname,
                t.email AS teacher_email

            FROM {assign_grades} ag
            JOIN {assign} a ON a.id = ag.assignment
            JOIN {course} c ON c.id = a.course
            JOIN {course_modules} cm ON cm.course = c.id AND cm.instance = a.id
            JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
            JOIN {user} u ON u.id = ag.userid AND u.deleted = 0
            JOIN {user} t ON t.id = ag.grader AND t.deleted = 0

            JOIN {context} ctx ON ctx.contextlevel = 50 AND ctx.instanceid = c.id
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = t.id
            JOIN {role} r ON r.id = ra.roleid AND r.shortname {$role_sql}

            LEFT JOIN {assignfeedback_comments} afc
                ON afc.grade = ag.id
                AND afc.assignment = a.id

            WHERE c.category {$category_sql}
              AND ag.grade IS NOT NULL
              AND ag.grade >= 0
              AND (
                    afc.id IS NULL
                    OR afc.commenttext IS NULL
                    OR TRIM(afc.commenttext) = ''
                    OR TRIM(afc.commenttext) = '<p></p>'
                    OR TRIM(afc.commenttext) = '<br>'
                    OR TRIM(afc.commenttext) = '<p><br></p>'
              )

            ORDER BY c.fullname, a.name, u.lastname, u.firstname
        ";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Fetch forum grades where the student started at least one discussion
     * and the teacher never replied in any of those student-started threads.
     *
     * @param string $category_sql IN/EQUAL clause for course categories.
     * @param string $role_sql IN/EQUAL clause for teacher role shortnames.
     * @param array $category_params Named parameters for $category_sql.
     * @param array $role_params Named parameters for $role_sql.
     * @return array Records keyed by grade id.
     * @throws \dml_exception
     */
    private function get_forum_rows($category_sql, $role_sql, $category_params, $role_params)
    {
        global $DB;

        $params = array_merge($category_params, $role_params);

        $sql = "
            SELECT
                gg.id AS grade_id,
                gg.finalgrade AS grade,
                gg.timemodified AS graded_at,
                gg.usermodified AS teacher_userid,
                gg.userid AS student_userid,

                f.id AS forum_id,
                f.name AS activity_name,

                c.id AS course_id,
                c.fullname AS course_name,
                c.shortname AS course_shortname,
                c.summary AS course_summary,

                cm.id AS module_id,

                u.idnumber AS student_idnumber,
                u.firstname AS student_firstname,
                u.lastname AS student_lastname,
                u.email AS student_email,

                t.idnumber AS teacher_idnumber,
                t.firstname AS teacher_firstname,
                t.lastname AS teacher_lastname,
                t.email AS teacher_email,

                (SELECT MIN(fd_link.id)
                   FROM {forum_discussions} fd_link
                  WHERE fd_link.forum = f.id
                    AND fd_link.userid = gg.userid) AS student_discussion_id

            FROM {forum} f
            JOIN {grade_items} gi
                ON gi.iteminstance = f.id
                AND gi.itemtype = 'mod'
                AND gi.itemmodule = 'forum'
                AND gi.itemnumber = CASE WHEN f.grade_forum > 0 THEN 1 ELSE 0 END
            JOIN {grade_grades} gg ON gg.itemid = gi.id
            JOIN {course} c ON c.id = f.course
            JOIN {course_modules} cm ON cm.course = c.id AND cm.instance = f.id
            JOIN {modules} m ON m.id = cm.module AND m.name = 'forum'
            JOIN {user} u ON u.id = gg.userid AND u.deleted = 0
            JOIN {user} t ON t.id = gg.usermodified AND t.deleted = 0

            JOIN {context} ctx ON ctx.contextlevel = 50 AND ctx.instanceid = c.id
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = t.id
            JOIN {role} r ON r.id = ra.roleid AND r.shortname {$role_sql}

            WHERE c.category {$category_sql}
              AND gg.finalgrade IS NOT NULL
              AND gg.usermodified > 0
              AND EXISTS (
                    SELECT 1
                      FROM {forum_discussions} fd
                     WHERE fd.forum = f.id
                       AND fd.userid = gg.userid
              )
              AND NOT EXISTS (
                    SELECT 1
                      FROM {forum_discussions} fd
                      JOIN {forum_posts} fp_t
                        ON fp_t.discussion = fd.id
                        AND fp_t.userid = gg.usermodified
                     WHERE fd.forum = f.id
                       AND fd.userid = gg.userid
              )

            ORDER BY c.fullname, f.name, u.lastname, u.firstname
        ";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Build per-course buckets and the flat data list used for the JSON cache.
     *
     * @param array $assignrows Rows from get_assign_rows().
     * @param array $forumrows Rows from get_forum_rows().
     * @param array $data Flat list of entries (passed by reference).
     * @return array Courses indexed by course id.
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function aggregate_by_course($assignrows, $forumrows, &$data)
    {
        $courses = [];

        foreach ($assignrows as $row) {
            $entry = $this->build_entry($row, 'assign');
            $data[] = $entry;
            $this->push_to_course($courses, $row, $entry);
        }

        foreach ($forumrows as $row) {
            $entry = $this->build_entry($row, 'forum');
            $data[] = $entry;
            $this->push_to_course($courses, $row, $entry);
        }

        return $courses;
    }

    /**
     * Build a normalized entry for a single graded item.
     *
     * @param \stdClass $row Raw DB row.
     * @param string $type Either 'assign' or 'forum'.
     * @return \stdClass
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function build_entry($row, $type)
    {
        $entry = new \stdClass();

        $entry->type = $type;
        $entry->course_id = $row->course_id;
        $entry->coursename = $row->course_name;
        $entry->shortname = $row->course_shortname;
        $entry->summary = strip_tags((string)$row->course_summary);

        $entry->activity = $row->activity_name;
        $entry->activity_id = $type === 'assign' ? $row->assign_id : $row->forum_id;

        $entry->teacher_userid = $row->teacher_userid;
        $entry->teacher_firstname = $row->teacher_firstname;
        $entry->teacher_lastname = $row->teacher_lastname;
        $entry->teacher_idnumber = $row->teacher_idnumber;
        $entry->teacher_email = $row->teacher_email;

        $entry->student_userid = $row->student_userid;
        $entry->student_firstname = $row->student_firstname;
        $entry->student_lastname = $row->student_lastname;
        $entry->student_idnumber = $row->student_idnumber;
        $entry->student_email = $row->student_email;

        $entry->grade = is_null($row->grade) ? '' : round((float)$row->grade, 2);
        $entry->graded_at = (int)$row->graded_at;
        $entry->graded_at_string = userdate($row->graded_at, get_string('strftimedatetimeshort', 'langconfig'));

        if ($type === 'assign') {
            $url = new moodle_url('/mod/assign/view.php', [
                'id'     => $row->module_id,
                'action' => 'grade',
                'userid' => $row->student_userid,
            ]);
        } else if (!empty($row->student_discussion_id)) {
            $url = new moodle_url('/mod/forum/discuss.php', ['d' => $row->student_discussion_id]);
        } else {
            $url = new moodle_url('/mod/forum/view.php', ['id' => $row->module_id]);
        }
        $entry->url = $url->out(false);

        return $entry;
    }

    /**
     * Append an entry to its course bucket, creating the bucket if needed.
     *
     * @param array $courses Courses bucket (passed by reference).
     * @param \stdClass $row Raw DB row used to seed the bucket metadata.
     * @param \stdClass $entry Normalized entry to append.
     * @return void
     */
    private function push_to_course(&$courses, $row, $entry)
    {
        $key = $row->course_id;
        if (!isset($courses[$key])) {
            $courses[$key] = [
                'course_id'        => $row->course_id,
                'course_name'      => $row->course_name,
                'course_shortname' => $row->course_shortname,
                'course_summary'   => strip_tags((string)$row->course_summary),
                'first_teacher_id' => $row->teacher_userid,
                'rows'             => [],
            ];
        }
        $courses[$key]['rows'][] = $entry;
    }

    /**
     * Build one DB record per course ready to be inserted into tool_tutor_follow_report.
     *
     * @param array $courses Courses bucket from aggregate_by_course().
     * @param string $cc_default Default CC email list.
     * @return array Records to insert.
     * @throws \coding_exception
     */
    private function build_course_records($courses, $cc_default)
    {
        $records = [];
        $now = time();

        foreach ($courses as $course) {
            $table = $this->generate_table($course['rows']);

            $record = new \stdClass();
            $record->status = 0;
            $record->authorid = $course['first_teacher_id'];
            $record->title = get_string('title_report_graded_without_feedback', 'tool_tutor_follow');
            $record->description = $this->build_description($course, $table);
            $record->cc_email = $cc_default;
            $record->cco_email = '';
            $record->timecreated = $now;
            $record->lasupdated = $now;

            $records[] = $record;
        }

        return $records;
    }

    /**
     * Build the HTML description shown in the report record.
     *
     * @param array $course Course bucket.
     * @param string $table_html Pre-rendered HTML table of pending feedback.
     * @return string
     * @throws \coding_exception
     */
    private function build_description($course, $table_html)
    {
        return '<strong>' . get_string('desc_course', 'tool_tutor_follow') . ':</strong> ' .
            $course['course_name'] . ' (' . $course['course_shortname'] . ')<br>' .
            '<strong>' . get_string('desc_pending_feedback', 'tool_tutor_follow') . ':</strong><br>' .
            $table_html;
    }

    /**
     * Render an HTML table with the rows pending feedback for a course.
     *
     * @param array $rows Normalized entries for the course.
     * @return string HTML table.
     * @throws \coding_exception
     */
    private function generate_table($rows)
    {
        $html = '<table style="border-collapse: collapse; width: 100%; border: 1px solid #ddd;">';
        $html .= '<thead><tr style="background-color: #f2f2f2;">';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_student', 'tool_tutor_follow') . '</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_idnumber', 'tool_tutor_follow') . '</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_activity', 'tool_tutor_follow') . '</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_grade', 'tool_tutor_follow') . '</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_graded_at', 'tool_tutor_follow') . '</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_teacher', 'tool_tutor_follow') . '</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">' . get_string('table_header_url', 'tool_tutor_follow') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $row->student_firstname . ' ' . $row->student_lastname . '</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $row->student_idnumber . '</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $row->activity . ' (' . $row->type . ')</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $row->grade . '</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $row->graded_at_string . '</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $row->teacher_firstname . ' ' . $row->teacher_lastname . '</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 8px;"><a href="' . $row->url . '" target="_blank">' . get_string('table_header_view', 'tool_tutor_follow') . '</a></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Download report as Excel file.
     *
     * @param string $shortname Report shortname used to locate the cached JSON.
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function download_report($shortname)
    {
        $rawdata = tool_tutor_follow_get_data("report_{$shortname}", 'reports');
        $data = json_decode($rawdata);

        $columns = [
            get_string('coursename', 'tool_tutor_follow'),
            get_string('course_shortname', 'tool_tutor_follow'),
            get_string('type', 'tool_tutor_follow'),
            get_string('nameactivity', 'tool_tutor_follow'),
            get_string('student_firstname', 'tool_tutor_follow'),
            get_string('student_lastname', 'tool_tutor_follow'),
            get_string('student_idnumber', 'tool_tutor_follow'),
            get_string('student_email', 'tool_tutor_follow'),
            get_string('table_header_grade', 'tool_tutor_follow'),
            get_string('table_header_graded_at', 'tool_tutor_follow'),
            get_string('teacher_firstname', 'tool_tutor_follow'),
            get_string('teacher_lastname', 'tool_tutor_follow'),
            get_string('teacher_email', 'tool_tutor_follow'),
            get_string('teacher_idnumber', 'tool_tutor_follow'),
            get_string('url', 'moodle'),
        ];

        $exportdata = [];
        foreach ((array)$data as $row) {
            $exportdata[] = [
                $row->coursename,
                $row->shortname,
                $row->type,
                $row->activity,
                $row->student_firstname,
                $row->student_lastname,
                $row->student_idnumber,
                $row->student_email,
                $row->grade,
                $row->graded_at_string,
                $row->teacher_firstname,
                $row->teacher_lastname,
                $row->teacher_email,
                $row->teacher_idnumber,
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
