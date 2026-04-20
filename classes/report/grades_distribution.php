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
 * @package   tool_tutor_follow
 * @copyright 2026, jrangelardila <jrangelardila@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tutor_follow\report;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/tutor_follow/lib.php');


class grades_distribution extends report_base
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

        list($category_sql, $category_params) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
        list($role_sql, $role_params) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'rol');

        $cc_config = get_config('tool_tutor_follow', 'cc_email_default');
        $cc_default = $cc_config ? implode(',', json_decode($cc_config)) : '';

        $threshold = (int) get_config('tool_tutor_follow', 'grades_distribution_threshold');
        if ($threshold <= 0) {
            $threshold = 40;
        }

        $min_students = (int) get_config('tool_tutor_follow', 'grades_distribution_min_students');
        if ($min_students <= 0) {
            $min_students = 6;
        }

        $week_end = strtotime('last sunday 00:00:00');
        $week_start = $week_end - (7 * DAYSECS);
        mtrace('Grading week range: ' . userdate($week_start) . ' -> ' . userdate($week_end));

        $data = [];
        $grouped = [];

        $params = array_merge($category_params, $role_params);

        mtrace('Execute for assigns...');

        $sql_assigns = "
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

        $activities = $DB->get_records_sql($sql_assigns, $params);

        foreach ($activities as $row) {
            $distribution_info = $this->get_grade_distribution(
                'assign',
                $row->assignid,
                $row->courseid,
                $week_start,
                $week_end,
                0
            );

            if ($distribution_info['total'] == 0) {
                continue;
            }

            if (empty($distribution_info['in_week'])) {
                continue;
            }

            if ($distribution_info['total'] <= $min_students) {
                continue;
            }

            $max_percentage = $this->get_max_percentage($distribution_info);
            if ($max_percentage < $threshold) {
                continue;
            }

            mtrace("Teacher: {$row->firstname} {$row->lastname} | Assign: {$row->assignname} | Graded: {$distribution_info['total']} | Max %: {$max_percentage}");

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
            $class->limitdatestring = userdate($row->limitdate, get_string('strftimedatetimeshort', 'langconfig'));
            $class->url = (new moodle_url('/mod/assign/view.php', ['id' => $row->moduleid]))->out(false);
            $class->total_graded = $distribution_info['total'];
            $class->grademax = $distribution_info['grademax'];
            $class->distribution = $this->filter_distribution($distribution_info['distribution'], $threshold);
            $class->max_percentage = $max_percentage;
            $class->first_graded = $distribution_info['first_graded'];
            $class->last_graded = $distribution_info['last_graded'];
            $class->first_graded_string = $distribution_info['first_graded']
                ? userdate($distribution_info['first_graded'], get_string('strftimedatetimeshort', 'langconfig'))
                : '';
            $class->last_graded_string = $distribution_info['last_graded']
                ? userdate($distribution_info['last_graded'], get_string('strftimedatetimeshort', 'langconfig'))
                : '';
            $class->execution = time();

            if (empty($class->distribution)) {
                continue;
            }

            $data[] = $class;
            $this->append_to_group($grouped, $row->tutorid, $row->courseid, $class);
        }

        mtrace('Execute for forums...');

        $params = array_merge($category_params, $role_params);

        $sql_forums = "
SELECT
    CONCAT(f.id, '_', tutor.id) AS uniqueid,
    f.id             AS forumid,
    f.name           AS forumname,
    f.duedate        AS limitdate,
    f.grade_forum,
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
  AND (f.assessed > 0 OR f.grade_forum > 0)
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

        $forums = $DB->get_records_sql($sql_forums, $params);

        foreach ($forums as $forum) {
            $distribution_info = $this->get_grade_distribution(
                'forum',
                $forum->forumid,
                $forum->courseid,
                $week_start,
                $week_end,
                (!empty($forum->grade_forum)) ? 1 : 0
            );

            if ($distribution_info['total'] == 0) {
                continue;
            }

            if (empty($distribution_info['in_week'])) {
                continue;
            }

            if ($distribution_info['total'] <= $min_students) {
                continue;
            }

            $max_percentage = $this->get_max_percentage($distribution_info);
            if ($max_percentage < $threshold) {
                continue;
            }

            mtrace("Teacher: {$forum->firstname} {$forum->lastname} | Forum: {$forum->forumname} | Graded: {$distribution_info['total']} | Max %: {$max_percentage}");

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
            $class->limitdatestring = userdate($forum->limitdate, get_string('strftimedatetimeshort', 'langconfig'));
            $class->url = (new moodle_url('/mod/forum/view.php', ['id' => $forum->moduleid]))->out(false);
            $class->total_graded = $distribution_info['total'];
            $class->grademax = $distribution_info['grademax'];
            $class->distribution = $this->filter_distribution($distribution_info['distribution'], $threshold);
            $class->max_percentage = $max_percentage;
            $class->first_graded = $distribution_info['first_graded'];
            $class->last_graded = $distribution_info['last_graded'];
            $class->first_graded_string = $distribution_info['first_graded']
                ? userdate($distribution_info['first_graded'], get_string('strftimedatetimeshort', 'langconfig'))
                : '';
            $class->last_graded_string = $distribution_info['last_graded']
                ? userdate($distribution_info['last_graded'], get_string('strftimedatetimeshort', 'langconfig'))
                : '';
            $class->execution = time();

            if (empty($class->distribution)) {
                continue;
            }

            $data[] = $class;
            $this->append_to_group($grouped, $forum->tutorid, $forum->courseid, $class);
        }

        $records = [];
        foreach ($grouped as $group) {
            $record = new \stdClass();
            $record->status = 0;
            $record->authorid = $group->tutorid;
            $record->title = get_string('title_report_grades_distribution', 'tool_tutor_follow');
            $record->description = $this->build_course_description($group);
            $record->cc_email = $cc_default;
            $record->timecreated = time();
            $record->lasupdated = time();
            $records[] = $record;
        }

        if (!empty($records)) {
            $DB->insert_records('tool_tutor_follow_report', $records);
        }

        tool_tutor_follow_save_data_json(
            json_encode($data),
            'report_grades_distribution',
            'reports'
        );
    }

    /**
     * Keep only distribution entries whose percentage reaches the threshold.
     *
     * @param array $distribution
     * @param float $threshold
     * @return array
     */
    private function filter_distribution(array $distribution, $threshold)
    {
        $filtered = [];
        foreach ($distribution as $entry) {
            if (isset($entry->percentage) && $entry->percentage >= $threshold) {
                $filtered[] = $entry;
            }
        }
        return $filtered;
    }

    /**
     * Append an activity to a (teacher, course) group.
     *
     * @param array $grouped
     * @param int $tutorid
     * @param int $courseid
     * @param \stdClass $class
     * @return void
     */
    private function append_to_group(array &$grouped, $tutorid, $courseid, \stdClass $class)
    {
        $key = (string) $courseid;
        if (!isset($grouped[$key])) {
            $group = new \stdClass();
            $group->tutorid = $tutorid;
            $group->firstname = $class->firstname;
            $group->lastname = $class->lastname;
            $group->coursename = $class->coursename;
            $group->shortname = $class->shortname;
            $group->summary = $class->summary;
            $group->activities = [];
            $grouped[$key] = $group;
        }
        $grouped[$key]->activities[] = $class;
    }

    /**
     * Compute highest percentage concentrated in a single grade bucket.
     *
     * @param array $distribution_info
     * @return float
     */
    private function get_max_percentage(array $distribution_info)
    {
        if ($distribution_info['total'] <= 0) {
            return 0;
        }
        $max_count = 0;
        foreach ($distribution_info['distribution'] as $entry) {
            if ($entry->count > $max_count) {
                $max_count = $entry->count;
            }
        }
        return round(($max_count / $distribution_info['total']) * 100, 2);
    }

    /**
     * Retrieve grade distribution for an activity.
     *
     * @param string $itemmodule
     * @param int $iteminstance
     * @param int $courseid
     * @return array
     * @throws \dml_exception
     */
    private function get_grade_distribution($itemmodule, $iteminstance, $courseid, $week_start = 0, $week_end = 0, $itemnumber = null)
    {
        global $DB;

        $criteria = [
            'itemtype'     => 'mod',
            'itemmodule'   => $itemmodule,
            'iteminstance' => $iteminstance,
            'courseid'     => $courseid,
        ];
        if ($itemnumber !== null) {
            $criteria['itemnumber'] = $itemnumber;
        }
        $gradeitems = $DB->get_records('grade_items', $criteria);

        $result = [
            'total' => 0,
            'grademax' => 0,
            'distribution' => [],
            'first_graded' => 0,
            'last_graded' => 0,
            'in_week' => false,
            'students' => [],
        ];

        if (empty($gradeitems)) {
            return $result;
        }

        $itemids = [];
        foreach ($gradeitems as $gi) {
            $itemids[] = $gi->id;
            if ((float) $gi->grademax > $result['grademax']) {
                $result['grademax'] = (float) $gi->grademax;
            }
        }

        list($insql, $inparams) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'gi');

        $sql = "
            SELECT gg.id, gg.finalgrade, gg.timemodified,
                   u.id AS studentid, u.firstname AS student_firstname, u.lastname AS student_lastname,
                   u.email AS student_email, u.idnumber AS student_idnumber
              FROM {grade_grades} gg
              JOIN {user} u ON u.id = gg.userid
              JOIN {user_enrolments} ue ON ue.userid = u.id
              JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
              JOIN {context} ctx ON ctx.instanceid = :courseid2 AND ctx.contextlevel = 50
              JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ctx.id
              JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'student'
             WHERE gg.itemid $insql
               AND gg.finalgrade IS NOT NULL
               AND gg.finalgrade > 0
               AND u.deleted = 0
               AND u.suspended = 0
               AND ue.status = 0
               AND e.status = 0
        ";

        $params = $inparams;
        $params['courseid'] = $courseid;
        $params['courseid2'] = $courseid;

        $grades = $DB->get_records_sql($sql, $params);

        $buckets = [];
        $students_by_bucket = [];
        $total = 0;
        $first_graded = 0;
        $last_graded = 0;
        $in_week = false;
        foreach ($grades as $g) {
            $value = round((float) $g->finalgrade, 2);
            if ($value <= 0) {
                continue;
            }
            $key = number_format($value, 2, '.', '');
            if (!isset($buckets[$key])) {
                $buckets[$key] = 0;
                $students_by_bucket[$key] = [];
            }
            $buckets[$key]++;
            $total++;

            $tm = (int) $g->timemodified;

            $student = new \stdClass();
            $student->studentid = (int) $g->studentid;
            $student->firstname = $g->student_firstname;
            $student->lastname = $g->student_lastname;
            $student->email = $g->student_email;
            $student->idnumber = $g->student_idnumber;
            $student->grade = $value;
            $student->graded_at = $tm;
            $student->graded_at_string = $tm > 0
                ? userdate($tm, get_string('strftimedatetimeshort', 'langconfig'))
                : '';
            $students_by_bucket[$key][] = $student;

            if ($tm > 0) {
                if ($first_graded === 0 || $tm < $first_graded) {
                    $first_graded = $tm;
                }
                if ($tm > $last_graded) {
                    $last_graded = $tm;
                }
                if ($week_start > 0 && $week_end > 0 && $tm >= $week_start && $tm < $week_end) {
                    $in_week = true;
                }
            }
        }

        uksort($buckets, function ($a, $b) {
            return (float) $a <=> (float) $b;
        });

        $distribution = [];
        foreach ($buckets as $grade => $count) {
            $entry = new \stdClass();
            $entry->grade = (float) $grade;
            $entry->count = $count;
            $entry->percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
            $entry->students = isset($students_by_bucket[$grade]) ? $students_by_bucket[$grade] : [];
            $distribution[] = $entry;
        }

        $result['total'] = $total;
        $result['distribution'] = $distribution;
        $result['first_graded'] = $first_graded;
        $result['last_graded'] = $last_graded;
        $result['in_week'] = $in_week;

        return $result;
    }

    /**
     * Build one description per (teacher, course) grouping all flagged activities.
     *
     * @param \stdClass $group
     * @return string
     * @throws \coding_exception
     */
    private function build_course_description(\stdClass $group)
    {
        $blocks = '';
        foreach ($group->activities as $class) {
            $rows = '';
            foreach ($class->distribution as $entry) {
                $rows .= '<tr>'
                    . '<td style="border:1px solid #ddd;padding:6px;text-align:center;">' . number_format((float) $entry->grade, 2, '.', '') . '</td>'
                    . '<td style="border:1px solid #ddd;padding:6px;text-align:center;">' . $entry->count . '</td>'
                    . '<td style="border:1px solid #ddd;padding:6px;text-align:center;">' . (isset($entry->percentage) ? $entry->percentage . '%' : '') . '</td>'
                    . '</tr>';
            }

            $table = '<table style="border-collapse:collapse;width:60%;border:1px solid #ddd;margin-bottom:12px;">'
                . '<thead><tr style="background-color:#f2f2f2;">'
                . '<th style="border:1px solid #ddd;padding:6px;">' . get_string('grades_distribution_grade', 'tool_tutor_follow') . '</th>'
                . '<th style="border:1px solid #ddd;padding:6px;">' . get_string('grades_distribution_count', 'tool_tutor_follow') . '</th>'
                . '<th style="border:1px solid #ddd;padding:6px;">' . get_string('grades_distribution_percentage', 'tool_tutor_follow') . '</th>'
                . '</tr></thead><tbody>' . $rows . '</tbody></table>';

            $students_table = $this->build_students_table($class->distribution);

            $blocks .= '<div style="margin-bottom:14px;">'
                . '<strong>' . get_string('nameactivity', 'tool_tutor_follow') . ':</strong> '
                . '<a href="' . $class->url . '" target="_blank">' . $class->activity . '</a> '
                . '(' . $class->type . ')<br>'
                . '<strong>' . get_string('grades_distribution_total', 'tool_tutor_follow') . ':</strong> ' . $class->total_graded . '<br>'
                . $table
                . $students_table
                . '</div>';
        }

        return get_string('grades_distribution_course_desc', 'tool_tutor_follow', [
            'firstname' => $group->firstname,
            'lastname' => $group->lastname,
            'coursename' => $group->coursename,
            'shortname' => $group->shortname,
            'summary' => $group->summary,
            'count_activities' => count($group->activities),
            'blocks' => $blocks,
        ]);
    }

    /**
     * Render a table of graded students for an activity.
     *
     * @param array $distribution Filtered distribution entries, each with a students[] list.
     * @return string
     * @throws \coding_exception
     */
    private function build_students_table($distribution)
    {
        $rows = '';
        foreach ($distribution as $entry) {
            if (empty($entry->students)) {
                continue;
            }
            foreach ($entry->students as $student) {
                $rows .= '<tr>'
                    . '<td style="border:1px solid #ddd;padding:6px;">' . $student->firstname . ' ' . $student->lastname . '</td>'
                    . '<td style="border:1px solid #ddd;padding:6px;">' . $student->idnumber . '</td>'
                    . '<td style="border:1px solid #ddd;padding:6px;">' . $student->email . '</td>'
                    . '<td style="border:1px solid #ddd;padding:6px;text-align:center;">' . number_format((float) $student->grade, 2, '.', '') . '</td>'
                    . '<td style="border:1px solid #ddd;padding:6px;">' . $student->graded_at_string . '</td>'
                    . '</tr>';
            }
        }

        if ($rows === '') {
            return '';
        }

        return '<table style="border-collapse:collapse;width:100%;border:1px solid #ddd;margin-bottom:12px;">'
            . '<thead><tr style="background-color:#f2f2f2;">'
            . '<th style="border:1px solid #ddd;padding:6px;">' . get_string('table_header_student', 'tool_tutor_follow') . '</th>'
            . '<th style="border:1px solid #ddd;padding:6px;">' . get_string('table_header_idnumber', 'tool_tutor_follow') . '</th>'
            . '<th style="border:1px solid #ddd;padding:6px;">' . get_string('table_header_email', 'tool_tutor_follow') . '</th>'
            . '<th style="border:1px solid #ddd;padding:6px;">' . get_string('table_header_grade', 'tool_tutor_follow') . '</th>'
            . '<th style="border:1px solid #ddd;padding:6px;">' . get_string('table_header_graded_at', 'tool_tutor_follow') . '</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
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
            get_string('section', 'tool_tutor_follow'),
            get_string('type', 'tool_tutor_follow'),
            get_string('nameactivity', 'tool_tutor_follow'),
            get_string('limitdate', 'tool_tutor_follow'),
            get_string('grades_distribution_total', 'tool_tutor_follow'),
            get_string('grades_distribution_grade', 'tool_tutor_follow'),
            get_string('grades_distribution_count', 'tool_tutor_follow'),
            get_string('grades_distribution_percentage', 'tool_tutor_follow'),
            get_string('grades_distribution_first_graded', 'tool_tutor_follow'),
            get_string('grades_distribution_last_graded', 'tool_tutor_follow'),
            get_string('student_firstname', 'tool_tutor_follow'),
            get_string('student_lastname', 'tool_tutor_follow'),
            get_string('student_email', 'tool_tutor_follow'),
            get_string('student_idnumber', 'tool_tutor_follow'),
            get_string('table_header_graded_at', 'tool_tutor_follow'),
            get_string('url', 'moodle'),
        ];

        $exportdata = [];
        if (is_array($data)) {
            foreach ($data as $row) {
                if (empty($row->distribution)) {
                    continue;
                }
                foreach ($row->distribution as $entry) {
                    $students = isset($entry->students) ? $entry->students : [];
                    if (empty($students)) {
                        continue;
                    }
                    foreach ($students as $student) {
                        $exportdata[] = [
                            $row->firstname,
                            $row->lastname,
                            $row->email,
                            $row->idnumber,
                            $row->coursename,
                            $row->shortname,
                            strip_tags($row->summary),
                            $row->section,
                            $row->type,
                            $row->activity,
                            $row->limitdatestring,
                            $row->total_graded,
                            number_format((float) $entry->grade, 2, '.', ''),
                            (int) $entry->count,
                            isset($entry->percentage) ? $entry->percentage : '',
                            isset($row->first_graded_string) ? $row->first_graded_string : '',
                            isset($row->last_graded_string) ? $row->last_graded_string : '',
                            $student->firstname,
                            $student->lastname,
                            $student->email,
                            $student->idnumber,
                            isset($student->graded_at_string) ? $student->graded_at_string : '',
                            $row->url,
                        ];
                    }
                }
            }
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
