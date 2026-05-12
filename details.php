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

global $DB;

use tool_tutor_follow\task\data_user_tutor;

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');


require_login();

$PAGE->set_url(new moodle_url('/admin/tool/tutor_follow/details.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'tool_tutor_follow'));
$PAGE->set_heading(get_string('pluginname', 'tool_tutor_follow'));
$PAGE->set_pagelayout('base');
$PAGE->requires->jquery();

require_capability('tool/tutor_follow:view', context_system::instance());

$PAGE->navbar->add(get_string('pluginname', 'tool_tutor_follow'), new moodle_url('/admin/tool/tutor_follow/index.php'));
$PAGE->navbar->add(get_string('details', 'tool_tutor_follow'));

echo $OUTPUT->header();
echo '
<style>
/* Details page */
.dtl-hero{background:#000;border-radius:16px;padding:1.75rem 2rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden}
.dtl-hero::before{content:"";position:absolute;inset:0;background:rgba(0,0,0,.18);pointer-events:none}
.dtl-hero>*{position:relative}
.dtl-hero-meta{font-size:.78rem;color:rgba(255,255,255,.5);margin-bottom:.6rem}
.dtl-hero-meta strong{color:rgba(255,255,255,.8)}
.dtl-hero-title{font-size:1.35rem;font-weight:800;color:#fff;display:inline-flex;align-items:center;gap:.5rem}
.dtl-hero-title:hover{color:rgba(255,255,255,.75);text-decoration:none}
.dtl-kpi-row{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}
.dtl-kpi-pill{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.14);border-radius:10px;padding:.4rem .85rem;font-size:.76rem;color:rgba(255,255,255,.8);display:flex;align-items:center;gap:.4rem}
.dtl-kpi-val{font-size:1rem;font-weight:800;color:#fff}
.dtl-kpi-danger{border-color:rgba(239,71,111,.5);background:rgba(239,71,111,.15)}
.dtl-kpi-success{border-color:rgba(6,214,160,.4);background:rgba(6,214,160,.12)}
.dtl-kpi-warning{border-color:rgba(253,126,20,.5);background:rgba(253,126,20,.15)}

.dtl-card{background:#fff;border-radius:14px;box-shadow:0 2px 16px rgba(0,0,0,.07);margin-bottom:1.4rem;overflow:hidden}
.dtl-card-head{display:flex;align-items:center;gap:.65rem;padding:.85rem 1.25rem;border-bottom:1px solid #f1f5f9;font-weight:700;font-size:.9rem;color:#1e293b}
.dtl-card-head-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.82rem;color:#fff;flex-shrink:0}
.dtl-card-body{padding:.85rem 1.25rem}

.dtl-tbl{width:100%;border-collapse:collapse;font-size:.83rem}
.dtl-tbl thead th{background:var(--bs-primary);padding:.55rem .85rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#fff;font-weight:700;border:1px solid rgba(0,0,0,.08);white-space:nowrap}
.dtl-tbl tbody td{padding:.55rem .85rem;border:1px solid #e2e8f0;color:#1e293b;vertical-align:middle}
.dtl-tbl tbody tr:hover{background:#f8fafc}

.dtl-activity{background:#fff;border-radius:14px;box-shadow:0 2px 16px rgba(0,0,0,.07);margin-bottom:1.2rem;overflow:hidden;border-left:4px solid var(--bs-primary)}
.dtl-forum{border-left-color:var(--bs-primary)}
.dtl-assign{border-left-color:var(--bs-secondary)}
.dtl-activity-head{padding:.9rem 1.25rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:.85rem;flex-wrap:wrap}
.dtl-activity-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#fff;flex-shrink:0}
.dtl-activity-name{font-weight:700;font-size:.93rem;color:#1e293b;flex:1;min-width:0}
.dtl-activity-name:hover{color:var(--bs-primary);text-decoration:none}
.dtl-activity-dates{font-size:.74rem;color:#94a3b8;padding:.4rem 1.25rem;background:#f8fafc;border-bottom:1px solid #f1f5f9}
.dtl-activity-dates span{margin-right:1.2rem}
.dtl-badges{display:flex;gap:.35rem;flex-wrap:wrap}
.dtl-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.18rem .6rem;border-radius:20px;font-size:.74rem;font-weight:700}
.dtl-badge-blue{background:rgba(0,0,0,.06);color:var(--bs-primary)}
.dtl-badge-teal{background:#f0fdf4;color:#16a34a}
.dtl-badge-red{background:#fef2f2;color:#dc2626}
.dtl-badge-purple{background:rgba(0,0,0,.06);color:var(--bs-secondary)}
.dtl-badge-gray{background:#f8fafc;color:#64748b}

.dtl-details{border-top:1px solid #f1f5f9}
.dtl-details summary{padding:.65rem 1.25rem;cursor:pointer;font-size:.81rem;font-weight:600;color:var(--bs-primary);list-style:none;display:flex;align-items:center;gap:.45rem;user-select:none;transition:background .15s}
.dtl-details summary:hover{background:#f8fafc}
.dtl-details summary::-webkit-details-marker{display:none}
.dtl-details summary::before{content:"▶";font-size:.6rem;color:#94a3b8;transition:transform .2s;display:inline-block}
.dtl-details[open] summary::before{transform:rotate(90deg)}
.dtl-details-body{padding:.75rem 1.25rem 1rem;overflow-x:auto;color:#1e293b;background:#fff}

.dtl-inner-tbl{width:100%;border-collapse:collapse;font-size:.83rem;min-width:680px}
.dtl-inner-tbl thead th{background:var(--bs-primary);color:#fff;padding:.55rem .85rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;border:1px solid rgba(0,0,0,.08);white-space:nowrap}
.dtl-inner-tbl tbody td{padding:.55rem .85rem;border:1px solid #e2e8f0;vertical-align:middle;color:#1e293b}
.dtl-inner-tbl tbody tr:hover{background:#f8fafc}
.dtl-feedback{max-width:380px;font-size:.78rem;color:#475569;word-break:break-word}

.dtl-dist-tbl{border-collapse:collapse;font-size:.83rem;max-width:260px;margin-bottom:.75rem}
.dtl-dist-tbl th{background:var(--bs-primary);color:#fff;padding:.55rem .85rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;border:1px solid rgba(0,0,0,.08)}
.dtl-dist-tbl td{padding:.55rem .85rem;border:1px solid #e2e8f0;text-align:center;color:#1e293b}
.dtl-section-title{font-size:1rem;font-weight:800;color:#1e293b;margin:1.5rem 0 .75rem;display:flex;align-items:center;gap:.5rem}
.dtl-section-title::after{content:"";flex:1;height:2px;background:#f1f5f9;border-radius:2px;margin-left:.5rem}
</style>';

echo html_writer::tag('img', "", [
    "src" => new moodle_url('/admin/tool/tutor_follow/img.png'),
    'class' => 'w-100'
]);
echo html_writer::tag('hr', '');
echo html_writer::tag('h2', get_string('hi', 'tool_tutor_follow') . fullname($USER), [
    'class' => 'text-left text-primary',
]);

tool_tutor_follow_print_bar($OUTPUT, 1, [
    get_string('grades', 'tool_tutor_follow'),
    get_string('send_reports', 'tool_tutor_follow'),
    get_string('reports', 'tool_tutor_follow'),
    get_string('studentsdistribution', 'tool_tutor_follow'),
    get_string('settings', 'tool_tutor_follow'),
]);


$courseid = optional_param("courseid", 0, PARAM_INT);
$userid   = optional_param("userid",   0, PARAM_INT);

if (empty($courseid) && empty($userid)) {
    throw new moodle_exception('missingcourseid', 'tool_tutor_follow');
}

$form = new \tool_tutor_follow\form\filter_user_data(
    action: new moodle_url('/admin/tool/tutor_follow/details.php', [
        'courseid' => $courseid,
        'userid'   => $userid,
    ]),
    method: 'get',
    elements: ['endtime']
);
$form->display();

$endtime = $form->get_data()->endtime ?? null;

$export_params = ['courseid' => $courseid, 'userid' => $userid, 'endtime' => (int)$endtime];
$export_url    = new moodle_url('/admin/tool/tutor_follow/export_details.php', $export_params);
echo html_writer::start_div('text-right mb-3');
echo html_writer::link(
    $export_url,
    '<i class="fa fa-file-excel mr-1"></i>' . get_string('export_grading_status', 'tool_tutor_follow'),
    ['class' => 'btn btn-success', 'target' => '_blank']
);
echo html_writer::end_div();

if ($courseid) {
    tool_tutor_follow_details_table_course($courseid, $endtime);
} else {
    tool_tutor_follow_details_table_user($userid, $endtime);
}

echo $OUTPUT->footer();
