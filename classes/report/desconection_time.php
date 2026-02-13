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
 * Version metadata for the tool_tutor_follow plugin.
 *
 * @package   tool_tutor_follow
 * @copyright 2026, jrangelardila <jrangelardila@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tutor_follow\report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/tutor_follow/lib.php');

class desconection_time extends report_base
{

    /**
     * Execute report
     *
     * @param mixed $categories
     * @param mixed $roles
     * @return void
     */
    public function execute($categories, $roles)
    {
        global $DB;

        $dayslimit = get_config('tool_tutor_follow', 'days_limit');
        $desconection = time() - ($dayslimit * 24 * 60 * 60);
        $now = time();
        list($catinsql, $catparams) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
        list($roleinsql, $roleparams) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'role');
        $sql = "SELECT
        ue.id AS enrolmentid,
        u.id AS userid,
        u.idnumber AS useridnumber,
        u.email,
        u.firstname,
        u.lastname,
        c.fullname AS course_name,
        c.shortname AS course_shortname,
        ula.timeaccess AS lastaccess,
        c.summary,
        :execute AS timeexecute
    FROM {user} u
    JOIN {user_enrolments} ue ON ue.userid = u.id
    JOIN {enrol} e ON e.id = ue.enrolid
    JOIN {course} c ON c.id = e.courseid
    JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :ctxlevel
    JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ctx.id
    JOIN {role} r ON r.id = ra.roleid
    LEFT JOIN {user_lastaccess} ula ON (ula.userid = u.id AND ula.courseid = c.id)
    WHERE
        c.category $catinsql
        AND r.shortname $roleinsql
        AND ue.status = :ue_status
        AND e.status = :e_status
        AND (ue.timestart = 0 OR ue.timestart <= :ahora1)
        AND (ue.timeend = 0 OR ue.timeend >= :ahora2)
        AND u.deleted = 0
        AND u.suspended = 0
        AND (ula.timeaccess < :desconection OR ula.timeaccess IS NULL)
        AND c.id != 1
    ORDER BY u.id ASC";
        $params = array_merge($catparams, $roleparams, [
            'ctxlevel' => CONTEXT_COURSE,
            'ue_status' => ENROL_USER_ACTIVE,
            'e_status' => ENROL_INSTANCE_ENABLED,
            'ahora1' => $now,
            'ahora2' => $now,
            'desconection' => $desconection,
            'execute' => $now,
        ]);
        $records = $DB->get_records_sql($sql, $params);
        if (!$records) return;
        $usercourses = [];
        foreach ($records as $record) {
            $usercourses[$record->userid][] = $record;
        }
        $elements = [];
        $cc_config = get_config('tool_tutor_follow', 'cc_email_default');
        $cc_default = $cc_config ? implode(',', json_decode($cc_config)) : '';
        foreach ($usercourses as $userid => $courses) {
            $element = new \stdClass();
            $element->authorid = $userid;
            $element->title = get_string('title_report_desconection_time', 'tool_tutor_follow');
            $element->cc_email = $cc_default;
            $element->status = 0;
            $element->timecreated = time();
            $element->lasupdated = time();
            $li_items = "";
            foreach ($courses as $c) {
                $days = ($c->lastaccess) ? floor((time() - $c->lastaccess) / DAYSECS) : get_string('never', 'core');
                $a = new \stdClass();
                $a->username = $c->firstname . ' ' . $c->lastname;
                $a->coursename = $c->course_name;
                $a->coursesummary = format_text($c->summary, FORMAT_HTML);
                $a->days = $days;
                $li_items .= "<li>" . get_string('disconnection_desc', 'tool_tutor_follow', $a) . "</li>";
            }
            $element->description = "<ul>" . $li_items . "</ul>";
            $elements[] = $element;
        }
        if (!empty($elements)) {
            $DB->insert_records('tool_tutor_follow_report', $elements);
        }
        //save data in json
        tool_tutor_follow_save_data_json(
            json_encode($records),
            'report_desconection_time',
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
            get_string('lastaccess', 'moodle'),
            get_string('days_without_access', 'tool_tutor_follow'),
        ];

        $exportdata = [];
        foreach ($data as $row) {
            $days = ($row->lastaccess)
                ? floor(($row->timeexecute - $row->lastaccess) / DAYSECS)
                : 'N/A';

            $date = ($row->lastaccess)
                ? date('Y-m-d H:i:s', $row->lastaccess)
                : get_string('never', 'moodle');
            $exportdata[] = [
                $row->firstname,
                $row->lastname,
                $row->email,
                $row->useridnumber,
                $row->course_name,
                $row->course_shortname,
                strip_tags($row->summary),
                $date,
                $days
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
