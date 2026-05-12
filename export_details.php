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
 * Export grading status to multi-sheet xlsx for course or user detail view.
 *
 * @package    tool_tutor_follow
 * @copyright  2026 Jhon Rangel <jrangelardila@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use tool_tutor_follow\task\data_user_tutor;

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('tool/tutor_follow:view', context_system::instance());

global $DB;

$courseid = optional_param('courseid', 0, PARAM_INT);
$userid   = optional_param('userid',   0, PARAM_INT);
$endtime  = optional_param('endtime',  0, PARAM_INT) ?: null;

if (empty($courseid) && empty($userid)) {
    throw new moodle_exception('missingcourseid', 'tool_tutor_follow');
}

$is_user_view = !empty($userid);

$all_activities = [];

if ($courseid) {
    $rec = $DB->get_record('tool_tutor_follow', [
        'instance_id' => $courseid,
        'type'        => data_user_tutor::DATA_TYPE['COURSE'],
    ]);
    if ($rec) {
        $info = json_decode(base64_decode($rec->datajson));
        $all_activities[] = [
            'shortname' => $info->course->shortname ?? '',
            'fullname'  => $info->course->fullname  ?? '',
            'summary'   => strip_tags((string)($info->course->summary ?? '')),
            'info'      => $info,
        ];
    }
} else {
    $data = json_decode(tool_tutor_follow_get_data('json_user_data', 'data_user'));
    if ($data && !empty($data->users)) {
        foreach ($data->users as $u) {
            if ((int)$u->id !== $userid) {
                continue;
            }
            foreach ($u->cursos as $course) {
                $rec = $DB->get_record('tool_tutor_follow', [
                    'instance_id' => $course->id,
                    'type'        => data_user_tutor::DATA_TYPE['COURSE'],
                ]);
                if (!$rec) {
                    continue;
                }
                $info = json_decode(base64_decode($rec->datajson));
                $all_activities[] = [
                    'shortname' => $course->shortname ?? '',
                    'fullname'  => $course->fullname  ?? '',
                    'summary'   => strip_tags((string)($course->summary ?? '')),
                    'info'      => $info,
                ];
            }
            break;
        }
    }
}

function ttf_filter_forum_grades(array $grades, ?int $endtime): array {
    if (!$endtime) {
        return $grades;
    }
    return array_filter($grades, function($g) use ($endtime) {
        return ($g->time_created_forum ?? 0) <= $endtime && ($g->timemodified ?? 0) < $endtime;
    });
}

function ttf_filter_assign_grades(array $grades, ?int $endtime): array {
    if (!$endtime) {
        return $grades;
    }
    return array_filter($grades, function($g) use ($endtime) {
        return ($g->time_created_assign ?? 0) <= $endtime && ($g->timemodified ?? 0) < $endtime;
    });
}

function ttf_filter_without_feedback(array $items, ?int $endtime): array {
    if (!$endtime) {
        return $items;
    }
    return array_filter($items, function($item) use ($endtime) {
        return ($item->timemodified ?? 0) <= $endtime;
    });
}

function ttf_filter_pending_submissions(array $items, ?int $endtime): array {
    if (!$endtime) {
        return $items;
    }
    return array_filter($items, function($item) use ($endtime) {
        return ($item->time_created ?? 0) <= $endtime;
    });
}

function ttf_compute_distribution(array $grades): array {
    $buckets = [];
    foreach ($grades as $g) {
        $val = (string)round((float)($g->grade ?? 0), 1);
        $buckets[$val] = ($buckets[$val] ?? 0) + 1;
    }
    uksort($buckets, fn($a, $b) => (float)$a <=> (float)$b);
    $result = [];
    foreach ($buckets as $grade => $count) {
        $result[] = (object)['grade' => $grade, 'count' => $count];
    }
    return $result;
}

function ttf_style_header(Worksheet $sheet, int $col_count): void {
    $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_count);
    $range = 'A1:' . $last . '1';
    $sheet->getStyle($range)->getFont()->setBold(true);
    $sheet->getStyle($range)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFE2E8F0');
    for ($c = 1; $c <= $col_count; $c++) {
        $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

function ttf_course_prefix(bool $is_user_view, array $act): array {
    if (!$is_user_view) {
        return [];
    }
    return [$act['shortname'], $act['fullname'], $act['summary']];
}

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$str_forum  = get_string('forum', 'tool_tutor_follow');
$str_assign = get_string('assignment', 'tool_tutor_follow');

$user_prefix_headers = $is_user_view ? [
    get_string('shortname', 'tool_tutor_follow'),
    get_string('coursename', 'tool_tutor_follow'),
    get_string('course_summary', 'tool_tutor_follow'),
] : [];

$sh1 = new Worksheet(null, get_string('export_sheet_nofeedback', 'tool_tutor_follow'));
$spreadsheet->addSheet($sh1);

$h1 = array_merge($user_prefix_headers, [
    get_string('type', 'tool_tutor_follow'),
    get_string('nameactivity', 'tool_tutor_follow'),
    get_string('table_header_student', 'tool_tutor_follow'),
    get_string('table_header_idnumber', 'tool_tutor_follow'),
    get_string('table_header_grade', 'tool_tutor_follow'),
    get_string('table_header_graded_at', 'tool_tutor_follow'),
    get_string('table_header_url', 'tool_tutor_follow'),
]);

$sh1->fromArray($h1, null, 'A1');
$r1 = 2;

foreach ($all_activities as $act) {
    $prefix = ttf_course_prefix($is_user_view, $act);
    $info   = $act['info'];

    foreach ((array)($info->forums ?? []) as $forum) {
        $wf = ttf_filter_without_feedback((array)($forum->without_feedback ?? []), $endtime);
        foreach ($wf as $item) {
            $sh1->fromArray(array_merge($prefix, [
                $str_forum,
                $forum->name,
                $item->student_name,
                $item->student_idnumber,
                $item->grade,
                $item->graded_at_string,
                $item->url,
            ]), null, 'A' . $r1++);
        }
    }

    foreach ((array)($info->assigns ?? []) as $assign) {
        $wf = ttf_filter_without_feedback((array)($assign->without_feedback ?? []), $endtime);
        foreach ($wf as $item) {
            $sh1->fromArray(array_merge($prefix, [
                $str_assign,
                $assign->name,
                $item->student_name,
                $item->student_idnumber,
                $item->grade,
                $item->graded_at_string,
                $item->url,
            ]), null, 'A' . $r1++);
        }
    }
}
ttf_style_header($sh1, count($h1));

$sh2 = new Worksheet(null, get_string('export_sheet_distribution', 'tool_tutor_follow'));
$spreadsheet->addSheet($sh2);

$h2 = array_merge($user_prefix_headers, [
    get_string('type', 'tool_tutor_follow'),
    get_string('nameactivity', 'tool_tutor_follow'),
    get_string('grade', 'tool_tutor_follow'),
    get_string('assignment_count', 'tool_tutor_follow'),
]);

$sh2->fromArray($h2, null, 'A1');
$r2 = 2;

foreach ($all_activities as $act) {
    $prefix = ttf_course_prefix($is_user_view, $act);
    $info   = $act['info'];

    foreach ((array)($info->forums ?? []) as $forum) {
        if ($endtime) {
            $filtered = ttf_filter_forum_grades((array)($forum->grades_process ?? []), $endtime);
            $stats    = ttf_compute_distribution($filtered);
        } else {
            $stats = (array)($forum->stadistics_grades ?? []);
        }
        foreach ($stats as $stat) {
            $sh2->fromArray(array_merge($prefix, [
                $str_forum,
                $forum->name,
                $stat->grade,
                $stat->count,
            ]), null, 'A' . $r2++);
        }
    }

    foreach ((array)($info->assigns ?? []) as $assign) {
        if ($endtime) {
            $filtered = ttf_filter_assign_grades((array)($assign->grades_process ?? []), $endtime);
            $stats    = ttf_compute_distribution($filtered);
        } else {
            $stats = (array)($assign->stadistics_grades ?? []);
        }
        foreach ($stats as $stat) {
            $sh2->fromArray(array_merge($prefix, [
                $str_assign,
                $assign->name,
                $stat->grade,
                $stat->count,
            ]), null, 'A' . $r2++);
        }
    }
}
ttf_style_header($sh2, count($h2));

$sh3 = new Worksheet(null, get_string('export_sheet_details', 'tool_tutor_follow'));
$spreadsheet->addSheet($sh3);

$h3 = array_merge($user_prefix_headers, [
    get_string('type', 'tool_tutor_follow'),
    get_string('nameactivity', 'tool_tutor_follow'),
    get_string('student', 'tool_tutor_follow'),
    get_string('submission_date', 'tool_tutor_follow'),
    get_string('grader', 'tool_tutor_follow'),
    get_string('grading_date', 'tool_tutor_follow'),
    get_string('grade', 'tool_tutor_follow'),
    get_string('feedback', 'tool_tutor_follow'),
]);

$sh3->fromArray($h3, null, 'A1');
$r3 = 2;

foreach ($all_activities as $act) {
    $prefix = ttf_course_prefix($is_user_view, $act);
    $info   = $act['info'];

    foreach ((array)($info->forums ?? []) as $forum) {
        $grades = ttf_filter_forum_grades((array)($forum->grades_process ?? []), $endtime);
        foreach ($grades as $g) {
            $sub_date = isset($g->time_created_message_forum)
                ? $g->time_created_message_forum
                : (isset($g->time_created_forum) ? userdate($g->time_created_forum) : '');
            $sh3->fromArray(array_merge($prefix, [
                $str_forum,
                $forum->name,
                $g->user,
                $sub_date,
                $g->usermodified,
                userdate($g->timemodified),
                $g->grade,
                strip_tags((string)($g->feedbak ?? '')),
            ]), null, 'A' . $r3++);
        }
    }

    foreach ((array)($info->assigns ?? []) as $assign) {
        $grades = ttf_filter_assign_grades((array)($assign->grades_process ?? []), $endtime);
        foreach ($grades as $g) {
            $sub_date = isset($g->time_created_message_assign)
                ? $g->time_created_message_assign
                : (isset($g->time_created_assign) ? userdate($g->time_created_assign) : '');
            $sh3->fromArray(array_merge($prefix, [
                $str_assign,
                $assign->name,
                $g->user,
                $sub_date,
                $g->usermodified,
                userdate($g->timemodified),
                $g->grade,
                strip_tags((string)($g->feedbak ?? '')),
            ]), null, 'A' . $r3++);
        }
    }
}
ttf_style_header($sh3, count($h3));

$sh4 = new Worksheet(null, get_string('export_sheet_pending', 'tool_tutor_follow'));
$spreadsheet->addSheet($sh4);

$h4 = array_merge($user_prefix_headers, [
    get_string('type', 'tool_tutor_follow'),
    get_string('nameactivity', 'tool_tutor_follow'),
    get_string('table_header_student', 'tool_tutor_follow'),
    get_string('table_header_idnumber', 'tool_tutor_follow'),
    get_string('submission_date', 'tool_tutor_follow'),
    get_string('table_header_url', 'tool_tutor_follow'),
]);

$sh4->fromArray($h4, null, 'A1');
$r4 = 2;

foreach ($all_activities as $act) {
    $prefix = ttf_course_prefix($is_user_view, $act);
    $info   = $act['info'];

    foreach ((array)($info->forums ?? []) as $forum) {
        $pending = ttf_filter_pending_submissions((array)($forum->pending_submissions ?? []), $endtime);
        foreach ($pending as $item) {
            $sh4->fromArray(array_merge($prefix, [
                $str_forum,
                $forum->name,
                $item->student_name,
                $item->student_idnumber,
                $item->submission_date_string,
                $item->url,
            ]), null, 'A' . $r4++);
        }
    }

    foreach ((array)($info->assigns ?? []) as $assign) {
        $pending = ttf_filter_pending_submissions((array)($assign->pending_submissions ?? []), $endtime);
        foreach ($pending as $item) {
            $sh4->fromArray(array_merge($prefix, [
                $str_assign,
                $assign->name,
                $item->student_name,
                $item->student_idnumber,
                $item->submission_date_string,
                $item->url,
            ]), null, 'A' . $r4++);
        }
    }
}
ttf_style_header($sh4, count($h4));

$type     = $courseid ? 'curso_' . $courseid : 'docente_' . $userid;
$filename = 'estado_calificacion_' . $type . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;
