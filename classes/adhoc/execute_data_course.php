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

        $forum->total_posts = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid)
     FROM {forum_discussions} 
     WHERE forum = :forumid",
            ['forumid' => $forum->id]
        );

        $this->total_posts += $forum->total_posts;

        $forum->graded_posts = $DB->count_records_sql("
            SELECT COUNT(g.userid)
            FROM {grade_items} gi
JOIN {grade_grades} g ON gi.id = g.itemid
WHERE gi.iteminstance = :forumid
AND gi.itemmodule = 'forum' AND g.finalgrade!=0", ['forumid' => $forum->id]);


        $this->total_grades += $forum->graded_posts;

        $forum->pending_grades = $forum->total_posts - $forum->graded_posts;

        if ($forum->pending_grades < 0) $forum->pending_grades = 0;

        $this->total_pending_grades += $forum->pending_grades;

        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course);
        $forum->course_module = $cm->id;

        $url = new \moodle_url("/mod/forum/view.php?f=" . $forum->id);
        $forum->url = $url->out();
        $forum->grades_process = $this->process_grades_info('forum', $forum);
        $forum->stadistics_grades = $this->process_stadistics_grades('forum', $forum);
        $forum->json_stadistics_grades = json_encode($forum->stadistics_grades);

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
    private function process_stadistics_grades(string $type, $module)
    {
        $cm = get_coursemodule_from_instance($type, $module->id);

        $grade_item = grade_item::fetch([
            'courseid' => $cm->course,
            'iteminstance' => $cm->instance,
            'itemmodule' => $type,
        ]);

        $grades = $grade_item->get_final();
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

        $assign->total_submissions = $DB->count_records('assign_submission', [
            'assignment' => $assign->id,
            'status' => 'submitted'
        ]);

        $this->total_assignments += $assign->total_submissions;

        $assign->graded_submissions = $DB->count_records_select('assign_grades',
            'assignment = :assignment AND grade >= 0',
            ['assignment' => $assign->id]
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

        if (!$assign->cutoffdate) {
            $assign->message_cutoffdate = get_string('noclosingdate', 'tool_tutor_follow');
        }
        if (!$assign->cutoffdate) {
            $assign->message_allowsubmissionsfromdate = get_string('noclosingdate', 'tool_tutor_follow');
        }

        return $assign;
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

        $grade_item = grade_item::fetch([
            'courseid' => $cm->course,
            'iteminstance' => $cm->instance,
            'itemmodule' => $type,
        ]);

        $grades_info = [];
        $grades = $grade_item->get_final();
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
                        $info->time_created_message_assign = 'Sin entrega';
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
                        $info->time_created_message_forum = 'Sin entrega';
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
}