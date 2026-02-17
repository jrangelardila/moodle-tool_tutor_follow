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
 * @package   repository_pluginname
 * @copyright 2026, author_fullname <author_link>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tutor_follow\form;

use cache;
use moodleform;

defined('MOODLE_INTERNAL') || die();

class filter_reports extends moodleform
{
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true, $ajaxformdata = null)
    {
        $this->statusoptions = [
            0 => get_string('status_report_inprocess', 'tool_tutor_follow'),
            1 => get_string('status_report_sended', 'tool_tutor_follow'),
            2 => get_string('status_report_complete', 'tool_tutor_follow')
        ];
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $ajaxformdata);
    }

    /**
     * Definition form
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function definition()
    {
        $mform = $this->_form;
        $filters_cache = cache::make('tool_tutor_follow', 'form_cache');

        $options = [
            "" => get_string("all", 'moodle'),
            0 => get_string('status_report_inprocess', 'tool_tutor_follow'),
            1 => get_string('status_report_sended', 'tool_tutor_follow'),
            2 => get_string('status_report_complete', 'tool_tutor_follow'),
        ];

        $mform->addElement('select', 'status', get_string('status', 'tool_tutor_follow'), $options);


        $data = json_decode(tool_tutor_follow_get_data('json_user_data', 'data_user'));
        $values = [];
        foreach ($data->users as $user) {
            $values[$user->id] = $user->firstname . " " . $user->lastname . " " . $user->idnumber . " " . $user->email;
        }
        $values[0] = "";
        $mform->addElement('autocomplete', 'authorid', get_string('author', 'tool_tutor_follow'), $values, [
            'multiple' => true,
            'noselectionstring' => get_string('choosedots'),
        ]);
        $mform->setDefault('authorid', 0);
        $mform->setType('authorid', PARAM_INT);

        if ($filters_cache->get("status")) {
            $mform->setDefault('status', $filters_cache->get("status"));
        }
        if ($filters_cache->get("authorid")) {
            $mform->setDefault('authorid', $filters_cache->get("authorid"));
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * Return reports with status
     *
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_reports_filter(
        int    $offset = 0,
        int    $limit = 20,
        string $sort = 'timecreated',
        string $dir = 'DESC'
    )
    {
        global $DB, $USER;

        $where = [];
        $params = [];

        $filters_cache = cache::make('tool_tutor_follow', 'form_cache');
        if ($this->is_submitted()) {
            $filters_cache->set('status', $this->get_data()->status);
            $filters_cache->set('authorid', $this->get_data()->authorid);
            $data = $this->get_data();
        } else {
            $data = new \stdClass();
            if ($filters_cache->get("status")) {
                $data->status = $filters_cache->get("status");
            }
            if ($filters_cache->get("authorid")) {
                $data->authorid = $filters_cache->get("authorid");
            }
        }

        if (isset($data->status) && $data->status !== '') {
            $where[] = "status = :status";
            $params['status'] = $data->status;
        }

        if (!empty($data->authorid)) {
            $authors = array_filter((array)$data->authorid);

            if ($authors) {
                list($insql, $inparams) = $DB->get_in_or_equal(
                    $authors,
                    SQL_PARAMS_NAMED,
                    'auth'
                );
                $where[] = "authorid $insql";
                $params = array_merge($params, $inparams);
            }
        }

        $select = $where ? implode(' AND ', $where) : '1=1';

        $allowedcols = [
            'status',
            'authorid',
            'title',
            'timecreated',
            'lasupdated'
        ];

        if (!in_array($sort, $allowedcols, true)) {
            $sort = 'timecreated';
        }

        $dir = ($dir === 'ASC') ? 'ASC' : 'DESC';

        $sql = "
        SELECT *
          FROM {tool_tutor_follow_report}
         WHERE $select
         ORDER BY $sort $dir
    ";

        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Total records
     *
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function count_reports_filter(): int
    {
        global $DB;

        $where = [];
        $params = [];
        $filters_cache = cache::make('tool_tutor_follow', 'form_cache');
        if ($this->is_submitted()) {
            $filters_cache->set('status', $this->get_data()->status);
            $filters_cache->set('authorid', $this->get_data()->authorid);
            $data = $this->get_data();
        } else {
            $data = new \stdClass();
            if ($filters_cache->get("status")) {
                $data->status = $filters_cache->get("status");
            }
            if ($filters_cache->get("authorid")) {
                $data->authorid = $filters_cache->get("authorid");
            }
        }

        if (isset($data->status) && $data->status !== '') {
            $where[] = "status = :status";
            $params['status'] = $data->status;
        }

        if (!empty($data->authorid)) {
            $authors = array_filter($data->authorid);
            if (!empty($authors)) {
                list($insql, $inparams) = $DB->get_in_or_equal(
                    $authors,
                    SQL_PARAMS_NAMED,
                    'auth'
                );
                $where[] = "authorid $insql";
                $params = array_merge($params, $inparams);
            }
        }

        $select = $where ? implode(' AND ', $where) : '1=1';

        return $DB->count_records_select(
            'tool_tutor_follow_report',
            $select,
            $params
        );
    }
}