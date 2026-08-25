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

namespace tool_tutor_follow\adhoc;

require_once(__DIR__ . "/../../../../../lib/grade/grade_item.php");
require_once($CFG->libdir . '/gradelib.php');
require_once(__DIR__ . "/../../lib.php");
require_once($CFG->libdir . '/filelib.php');

defined('MOODLE_INTERNAL') || die();

use context_module;
use core\task\adhoc_task;
use grade_item;
use moodle_url;
use stdClass;
use tool_tutor_follow\task\data_user_tutor;

class execute_data_course extends adhoc_task
{
    private $total_assignments = 0;
    private $total_posts = 0;
    private $total_grades = 0;
    private $total_pending_grades = 0;

    /** @var array|null Ids de matriculados activos del curso, resueltos una sola vez. */
    private $activeuserids = null;

    /**
     * Ids de los usuarios con matrícula activa en el curso.
     * @return array Lista de userids.
     * @throws \dml_exception
     */
    private function get_active_userids(): array
    {
        global $DB;

        if ($this->activeuserids !== null) {
            return $this->activeuserids;
        }

        $this->activeuserids = $DB->get_fieldset_sql("
            SELECT DISTINCT u.id
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
              JOIN {user} u ON u.id = ue.userid
             WHERE e.courseid = :courseid
               AND ue.status = 0
               AND e.status = 0
               AND u.deleted = 0
               AND u.suspended = 0
        ", ['courseid' => $this->get_custom_data()->id]);

        return $this->activeuserids;
    }

    /**
     * Parametros SQL
     *
     * @param string $prefix Prefijo de los parámetros nombrados.
     * @return array [fragmento SQL, parámetros]
     * @throws \dml_exception
     */
    private function get_active_users_sql(string $prefix = 'au'): array
    {
        global $DB;

        $userids = $this->get_active_userids();

        if (empty($userids)) {
             return ['= :' . $prefix . 'none', [$prefix . 'none' => -1]];
        }

        return $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, $prefix);
    }

    /**
     * @inheritDoc
     * @throws \dml_exception
     */
    public function execute()
    {
        global $DB;

        $course = $this->get_custom_data();

        mtrace("Get data for " . $course->shortname . " - " . $course->fullname);
        mtrace("Get forums...");
        $sql = "
    SELECT f.*
    FROM {forum} f
    LEFT JOIN {grade_items} gi ON gi.iteminstance = f.id 
    WHERE f.course = :courseid AND gi.itemmodule = 'forum'
";
        $forums = $DB->get_records_sql($sql, ['courseid' => $course->id]);


        mtrace("Get assigns...");
        $assigns = $DB->get_records('assign', ['course' => $course->id]);

        $course->total_assign = sizeof($assigns);
        $course->total_forum = sizeof($forums);
        $course->students = tool_tutor_follow_get_count_students($course->id);

        $summary = [
            'course' => $course,
            'forums' => [],
            'assigns' => [],
            'timemodified' => time(),
        ];
        //Process each forum and save the resume
        foreach ($forums as $forum) {
            $summary['forums'][] = $this->process_forum_grades($forum);
        }

        //Process each assign and save the resume
        foreach ($assigns as $assign) {
            $summary['assigns'][] = $this->process_assign_grades($assign);
        }

        $course->total_posts = $this->total_posts;
        $course->total_assignments = $this->total_assignments;
        $course->total_grades = $this->total_grades;
        $course->total_pending_grades = $this->total_pending_grades;

        $this->save_data($summary);

    }

    /**
     * Save info
     * @param $data
     * @return void
     * @throws \dml_exception
     */
    protected function save_data($data)
    {
        global $DB;

        $record = $DB->get_record("tool_tutor_follow", [
            'instance_id' => $this->get_custom_data()->id,
            'type' => data_user_tutor::DATA_TYPE['COURSE'],
        ]);

        if ($record) {
            $record->datajson = base64_encode(json_encode($data));
            $record->lastupdate = time();

            $DB->update_record('tool_tutor_follow', $record);
        } else {
            $record = new stdClass();
            $record->type = data_user_tutor::DATA_TYPE['COURSE'];
            $record->instance_id = $this->get_custom_data()->id;
            $record->datajson = base64_encode(json_encode($data));
            $record->lastupdate = time();

            $DB->insert_record('tool_tutor_follow', $record);
        }
    }

    /**
     * Process grades and feedback in forums
     * @param $forum
     * @return mixed
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function process_forum_grades($forum)
    {
        global $DB;

        list($activesql, $activeparams) = $this->get_active_users_sql();

        $forum->total_posts = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid)
     FROM {forum_discussions}
     WHERE forum = :forumid
       AND userid $activesql",
            array_merge(['forumid' => $forum->id], $activeparams)
        );

        $this->total_posts += $forum->total_posts;

        $forum->graded_posts = $DB->count_records_sql("
            SELECT COUNT(DISTINCT gg.userid)
              FROM {grade_items} gi
              JOIN {grade_grades} gg ON gg.itemid = gi.id
             WHERE gi.itemmodule   = 'forum'
               AND gi.iteminstance = :forumid
               AND gi.itemnumber  IN (0, 1)
               AND gg.finalgrade IS NOT NULL
               AND gg.finalgrade   > 0
               AND gg.userid $activesql",
            array_merge(['forumid' => $forum->id], $activeparams));

        $this->total_grades += $forum->graded_posts;

        $forum->pending_grades = $DB->count_records_sql("
            SELECT COUNT(*) FROM (
                SELECT DISTINCT fd.userid
                  FROM {forum_discussions} fd
                 WHERE fd.forum = :forumid
                   AND fd.userid $activesql
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {grade_items} gi
                         JOIN {grade_grades} gg ON gg.itemid = gi.id
                        WHERE gi.itemmodule   = 'forum'
                          AND gi.iteminstance = :forumid2
                          AND gi.itemnumber  IN (0, 1)
                          AND gg.userid       = fd.userid
                          AND gg.finalgrade IS NOT NULL
                          AND gg.finalgrade   > 0
                   )
            ) t", array_merge(['forumid' => $forum->id, 'forumid2' => $forum->id], $activeparams));

        $this->total_pending_grades += $forum->pending_grades;

        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course);
        $forum->course_module = $cm->id;

        $url = new \moodle_url("/mod/forum/view.php?f=" . $forum->id);
        $forum->url = $url->out();
        $forum->grades_process = $this->process_grades_info('forum', $forum);
        $forum->stadistics_grades = $this->process_stadistics_grades('forum', $forum);
        $forum->json_stadistics_grades = json_encode($forum->stadistics_grades);
        $forum->without_feedback = $this->get_forum_without_feedback($forum, $cm);
        $forum->count_without_feedback = count($forum->without_feedback);
        $forum->pending_submissions = $this->get_forum_pending_submissions($forum, $cm);

        if (!$forum->cutoffdate) {
            $forum->message_cutoffdate = get_string('noclosingdate', 'tool_tutor_follow');
        }
        return $forum;
    }

    /**
     * Group the grades
     *
     * @param string $type
     * @param $module
     * @return array
     * @throws \coding_exception
     */
    /**
     * Notas finales por usuario del módulo. Para foros combina los DOS grade
     * items (valoraciones = itemnumber 0, foro completo = 1) tomando por usuario
     * la nota positiva (la mayor si tuviera en ambos), de modo que estadísticas y
     * listado de calificaciones no dependan de un único item elegido por un CASE.
     *
     * @param string $type
     * @param \stdClass $cm
     * @return array [userid => grade_grade]
     */
    private function get_module_final_grades(string $type, $cm): array
    {
        $itemnumbers = ($type === 'forum') ? [0, 1] : [0];
        // get_final() devuelve toda la libreta del item, incluidas las notas de
        // matrículas suspendidas; se descartan aquí para que estadísticas y
        // listado de calificaciones sólo reflejen a los matriculados activos.
        $active = array_flip($this->get_active_userids());
        $merged = [];
        foreach ($itemnumbers as $itemnumber) {
            $grade_item = grade_item::fetch([
                'courseid'     => $cm->course,
                'iteminstance' => $cm->instance,
                'itemmodule'   => $type,
                'itemnumber'   => $itemnumber,
            ]);
            if (!$grade_item) {
                continue;
            }
            foreach ($grade_item->get_final() as $userid => $grade_data) {
                if (empty($grade_data->finalgrade) || !isset($active[$userid])) {
                    continue;
                }
                if (!isset($merged[$userid]) || $grade_data->finalgrade > $merged[$userid]->finalgrade) {
                    $merged[$userid] = $grade_data;
                }
            }
        }
        return $merged;
    }

    private function process_stadistics_grades(string $type, $module)
    {
        $cm = get_coursemodule_from_instance($type, $module->id);

        $grades = $this->get_module_final_grades($type, $cm);
        $statistics = [];
        foreach ($grades as $userid => $grade_data) {
            if ($grade_data->finalgrade) {
                $finalgrade = round($grade_data->finalgrade, 1);
                $finalgrade = (string)$finalgrade;
                if (isset($statistics[$finalgrade])) {
                    $statistics[$finalgrade]++;
                } else {
                    $statistics[$finalgrade] = 1;
                }

            }
        }
        $grades_for_mustache = [];
        foreach ($statistics as $grade => $count) {
            $grades_for_mustache[] = [
                'grade' => $grade,
                'count' => $count,
            ];
        }

        usort($grades_for_mustache, function ($a, $b) {
            return $a['grade'] <=> $b['grade'];
        });

        return $grades_for_mustache;
    }

    /**
     * Process de calification and feedback
     * @param $assign
     * @return void
     * @throws \dml_exception
     * @throws \coding_exception|\moodle_exception
     */
    protected function process_assign_grades($assign)
    {
        global $DB;

        list($activesql, $activeparams) = $this->get_active_users_sql();

        $assign->total_submissions = $DB->count_records_select('assign_submission',
            "assignment = :assignment AND status = 'submitted' AND userid $activesql",
            array_merge(['assignment' => $assign->id], $activeparams)
        );

        $this->total_assignments += $assign->total_submissions;

        $assign->graded_submissions = $DB->count_records_select('assign_grades',
            "assignment = :assignment AND grade >= 0 AND userid $activesql",
            array_merge(['assignment' => $assign->id], $activeparams)
        );

        $assign->pending_grades = $assign->total_submissions - $assign->graded_submissions;

        if ($assign->pending_grades < 0) $assign->pending_grades = 0;

        $this->total_grades += $assign->graded_submissions;
        $this->total_pending_grades += $assign->pending_grades;

        $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course);
        $url = new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
        $assign->url = $url->out();

        $assign->course_module = $cm->id;

        $assign->grades_process = $this->process_grades_info('assign', $assign);
        $assign->stadistics_grades = $this->process_stadistics_grades('assign', $assign);
        $assign->json_stadistics_grades = json_encode($assign->stadistics_grades);
        $assign->without_feedback = $this->get_assign_without_feedback($assign, $cm);
        $assign->count_without_feedback = count($assign->without_feedback);
        $assign->pending_submissions = $this->get_assign_pending_submissions($assign, $cm);

        if (!$assign->cutoffdate) {
            $assign->message_cutoffdate = get_string('noclosingdate', 'tool_tutor_follow');
        }
        if (!$assign->cutoffdate) {
            $assign->message_allowsubmissionsfromdate = get_string('noclosingdate', 'tool_tutor_follow');
        }

        return $assign;
    }

    /**
     * Return assign grades without any meaningful feedback comment.
     */
    private function get_assign_without_feedback($assign, $cm)
    {
        global $DB;

        list($activesql, $activeparams) = $this->get_active_users_sql();

        $rows = $DB->get_records_sql("
            SELECT ag.id, ag.userid, ag.grade, ag.timemodified,
                   u.firstname, u.lastname, u.idnumber
              FROM {assign_grades} ag
              JOIN {user} u ON u.id = ag.userid AND u.deleted = 0
         LEFT JOIN {assignfeedback_comments} afc
                ON afc.grade = ag.id AND afc.assignment = :assignid2
             WHERE ag.assignment = :assignid
               AND ag.userid $activesql
               AND ag.grade IS NOT NULL
               AND ag.grade > 0
               AND (
                     afc.id IS NULL
                     OR afc.commenttext IS NULL
                     OR TRIM(afc.commenttext) = ''
                     OR TRIM(afc.commenttext) = '<p></p>'
                     OR TRIM(afc.commenttext) = '<br>'
                     OR TRIM(afc.commenttext) = '<p><br></p>'
               )
          ORDER BY u.lastname, u.firstname
        ", array_merge(['assignid' => $assign->id, 'assignid2' => $assign->id], $activeparams));

        $result = [];
        foreach ($rows as $row) {
            $entry = new stdClass();
            $entry->student_name     = $row->firstname . ' ' . $row->lastname;
            $entry->student_idnumber = $row->idnumber;
            $entry->grade            = round((float)$row->grade, 1);
            $entry->timemodified     = (int)$row->timemodified;
            $entry->graded_at_string = userdate($row->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
            $entry->url = (new moodle_url('/mod/assign/view.php', [
                'id'     => $cm->id,
                'action' => 'grade',
                'userid' => $row->userid,
            ]))->out(false);
            $result[] = $entry;
        }
        return $result;
    }

    /**
     * Return forum grades where the teacher never replied in the student's discussion.
     */
    private function get_forum_without_feedback($forum, $cm)
    {
        global $DB;

        list($activesql, $activeparams) = $this->get_active_users_sql();

        $rows = $DB->get_records_sql("
            SELECT gg.id, gg.userid, gg.finalgrade AS grade, gg.timemodified, gg.usermodified,
                   u.firstname, u.lastname, u.idnumber,
                   (SELECT MIN(fd2.id) FROM {forum_discussions} fd2
                     WHERE fd2.forum = :forumid2 AND fd2.userid = gg.userid) AS discussion_id
              FROM {grade_items} gi
              JOIN {grade_grades} gg ON gg.itemid = gi.id
              JOIN {user} u ON u.id = gg.userid AND u.deleted = 0
             WHERE gi.itemmodule = 'forum'
               AND gi.itemtype   = 'mod'
               AND gi.iteminstance = :forumid
               AND gi.itemnumber  IN (0, 1)
               AND gg.userid $activesql
               AND gg.finalgrade IS NOT NULL
               AND gg.finalgrade > 0
               AND gg.usermodified > 0
               AND EXISTS (
                     SELECT 1 FROM {forum_discussions} fd
                      WHERE fd.forum = :forumid3 AND fd.userid = gg.userid
               )
               AND NOT EXISTS (
                     SELECT 1 FROM {forum_discussions} fd
                      JOIN {forum_posts} fp ON fp.discussion = fd.id AND fp.userid = gg.usermodified
                     WHERE fd.forum = :forumid4 AND fd.userid = gg.userid
               )
          ORDER BY u.lastname, u.firstname
        ", array_merge([
            'forumid'    => $forum->id,
            'forumid2'   => $forum->id,
            'forumid3'   => $forum->id,
            'forumid4'   => $forum->id,
        ], $activeparams));

        $result = [];
        $seen   = [];
        foreach ($rows as $row) {
            if (isset($seen[$row->userid])) {
                continue; // Same student already counted (graded on both items).
            }
            $seen[$row->userid] = true;
            $entry = new stdClass();
            $entry->student_name     = $row->firstname . ' ' . $row->lastname;
            $entry->student_idnumber = $row->idnumber;
            $entry->grade            = round((float)$row->grade, 1);
            $entry->timemodified     = (int)$row->timemodified;
            $entry->graded_at_string = userdate($row->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
            if (!empty($row->discussion_id)) {
                $url = new moodle_url('/mod/forum/discuss.php', ['d' => $row->discussion_id]);
            } else {
                $url = new moodle_url('/mod/forum/view.php', ['id' => $cm->id]);
            }
            $entry->url = $url->out(false);
            $result[] = $entry;
        }
        return $result;
    }

    /**
     * Return actual grades
     *
     * @param $type
     * @param $module
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function process_grades_info($type, $module)
    {
        global $DB;
        $cm = get_coursemodule_from_instance($type, $module->id);

        $grades_info = [];
        $grades = $this->get_module_final_grades($type, $cm);
        foreach ($grades as $userid => $grade_data) {
            if ($grade_data->finalgrade) {
                $info = new stdClass();

                $user = $DB->get_record('user', ['id' => $userid]);
                $url = new moodle_url('/user/profile.php', array('id' => $user->id));

                $info->user = fullname($user);
                $info->user_url = $url->out();

                $info->userid = $userid;
                $info->grade = round($grade_data->finalgrade, 1);

                $info->timemodified = $grade_data->timemodified;

                $user = $DB->get_record('user', ['id' => $grade_data->usermodified]);
                $url = new moodle_url('/user/profile.php', array('id' => $user->id));
                $info->usermodified = fullname($user);
                $info->usermodified_url = $url->out();

                if ($type == 'assign') {
                    $submission = $DB->get_record('assign_submission', [
                        'assignment' => $cm->instance,
                        'userid' => $userid,
                        'status' => 'submitted'
                    ], 'timemodified');
                    $info->time_created_assign = $submission->timemodified;
                    if ($info->time_created_assign == 0) {
                        $info->time_created_message_assign = get_string('no_submission', 'tool_tutor_follow');
                    }
                    //fedback
                    $context = context_module::instance($cm->id);
                    $feedback = format_text($grade_data->feedback, $grade_data->feedbackformat, ['context' => $context]);
                    $info->feedbak = file_rewrite_pluginfile_urls($feedback, 'pluginfile.php', $context->id,
                        'grade', 'feedback', $grade_data->id);
                } elseif ($type == 'forum') {
                    $discuss = $DB->get_record('forum_discussions', [
                        'forum' => $cm->instance,
                        'userid' => $userid
                    ]);
                    $post = $DB->get_record('forum_posts', [
                        'id' => $discuss->firstpost,
                    ]);
                    $info->time_created_forum = $post->created;
                    if ($info->time_created_forum == 0) {
                        $info->time_created_message_forum = get_string('no_submission', 'tool_tutor_follow');
                    }
                    //Feedback for forums
                    $post_teacher = $DB->get_record('forum_posts', [
                        'discussion' => $discuss->id,
                        'userid' => $grade_data->usermodified
                    ]);
                    if ($info->feedbak == "") {
                        $context = context_module::instance($cm->id);
                        $feedbak = format_text($post_teacher->message, FORMAT_HTML, ['context' => $context]);
                        $info->feedbak = file_rewrite_pluginfile_urls($feedbak, 'pluginfile.php', $context->id,
                            'mod_forum', 'post', $post_teacher->id);
                    }
                }

                $grades_info[] = $info;
            }
        }

        usort($grades_info, function ($a, $b) {
            return $a->timemodified <=> $b->timemodified;
        });

        return $grades_info;
    }

    private function get_assign_pending_submissions($assign, $cm)
    {
        global $DB;

        list($activesql, $activeparams) = $this->get_active_users_sql();

        $rows = $DB->get_records_sql("
            SELECT asub.id, asub.userid, asub.timemodified,
                   u.firstname, u.lastname, u.idnumber
              FROM {assign_submission} asub
              JOIN {user} u ON u.id = asub.userid AND u.deleted = 0
         LEFT JOIN {assign_grades} ag
                   ON ag.assignment = asub.assignment
                  AND ag.userid     = asub.userid
                  AND ag.grade >= 0
             WHERE asub.assignment = :assignid
               AND asub.userid $activesql
               AND asub.status = 'submitted'
               AND asub.latest = 1
               AND ag.id IS NULL
             ORDER BY u.lastname, u.firstname
        ", array_merge(['assignid' => $assign->id], $activeparams));

        $result = [];
        foreach ($rows as $row) {
            $entry = new stdClass();
            $entry->student_name           = $row->firstname . ' ' . $row->lastname;
            $entry->student_idnumber       = $row->idnumber;
            $entry->time_created           = (int)$row->timemodified;
            $entry->submission_date_string = userdate($row->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
            $entry->url = (new moodle_url('/mod/assign/view.php', [
                'id'     => $cm->id,
                'action' => 'grade',
                'userid' => $row->userid,
            ]))->out(false);
            $result[] = $entry;
        }
        return $result;
    }

    private function get_forum_pending_submissions($forum, $cm)
    {
        global $DB;

        list($activesql, $activeparams) = $this->get_active_users_sql();

        $rows = $DB->get_records_sql("
            SELECT fd.userid,
                   u.firstname, u.lastname, u.idnumber,
                   fd.id AS discussion_id,
                   (SELECT MIN(fp2.created) FROM {forum_posts} fp2
                     WHERE fp2.discussion = fd.id AND fp2.parent = 0) AS time_created
              FROM {forum_discussions} fd
              JOIN {user} u ON u.id = fd.userid AND u.deleted = 0
             WHERE fd.forum = :forumid
               AND fd.userid $activesql
               AND NOT EXISTS (
                       SELECT 1
                         FROM {grade_items} gi
                         JOIN {grade_grades} gg ON gg.itemid = gi.id
                        WHERE gi.itemmodule   = 'forum'
                          AND gi.iteminstance = :forumid2
                          AND gi.itemnumber  IN (0, 1)
                          AND gg.userid       = fd.userid
                          AND gg.finalgrade IS NOT NULL
                          AND gg.finalgrade   > 0
               )
             ORDER BY u.lastname, u.firstname
        ", array_merge([
            'forumid'    => $forum->id,
            'forumid2'   => $forum->id,
        ], $activeparams));

        $result = [];
        foreach ($rows as $row) {
            $entry = new stdClass();
            $entry->student_name           = $row->firstname . ' ' . $row->lastname;
            $entry->student_idnumber       = $row->idnumber;
            $entry->time_created           = (int)($row->time_created ?? 0);
            $entry->submission_date_string = $entry->time_created
                ? userdate($entry->time_created, get_string('strftimedatetimeshort', 'langconfig'))
                : '';
            if (!empty($row->discussion_id)) {
                $url = new moodle_url('/mod/forum/discuss.php', ['d' => $row->discussion_id]);
            } else {
                $url = new moodle_url('/mod/forum/view.php', ['id' => $cm->id]);
            }
            $entry->url = $url->out(false);
            $result[] = $entry;
        }
        return $result;
    }
}