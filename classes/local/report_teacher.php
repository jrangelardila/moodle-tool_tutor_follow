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

namespace tool_tutor_follow\local;

require_once(__DIR__ . '/../../lib.php');

use context;
use core_form\dynamic_form;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class report_teacher extends dynamic_form
{
    /**
     * @var mixed|string
     */
    private mixed $type;

    /**
     * Types de forms
     */
    const TYPES = [
        'created' => 0,
        'show' => 1,
        'filter' => 2,
        'edit' => 3,
        'update' => 4,
    ];
    private array $statusoptions;
    //Elements for the db table
    private $id;
    private $status;
    private $authorid;
    private $title;
    private $description;
    private $cc_email;
    private $cco_email;
    private $timecreated;
    private $lasupdated;

    /**
     * @throws \coding_exception
     */
    public function __construct(?string $action = null, ?array $customdata = null, string $method = 'post', string $target = '', ?array $attributes = [], bool $editable = true, ?array $ajaxformdata = null, bool $isajaxsubmission = false,
                                        $type = self::TYPES['created'])
    {
        $this->type = $type;

        $this->statusoptions = [
            0 => get_string('status_report_inprocess', 'tool_tutor_follow'),
            1 => get_string('status_report_sended', 'tool_tutor_follow'),
            2 => get_string('status_report_complete', 'tool_tutor_follow')
        ];
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $ajaxformdata, $isajaxsubmission);
    }

    /**
     * Return context
     *
     * @return context
     * @throws \dml_exception
     */
    protected function get_context_for_dynamic_submission(): context
    {
        return \context_system::instance();
    }

    protected function check_access_for_dynamic_submission(): void
    {
        // TODO: Implement check_access_for_dynamic_submission() method.
    }

    /**
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function process_dynamic_submission()
    {
        if ($this->optional_param('type', 'created', PARAM_TEXT)) {
            $this->type = self::TYPES[$this->optional_param('type', 'created', PARAM_TEXT)];
        }
        switch ($this->type) {
            case self::TYPES['created']:
            case self::TYPES['edit']:
                $data = $this->get_data();
                $this->status = $data->status;
                $this->authorid = $data->authorid;
                $this->title = $data->title;
                $this->description = $data->description['text'];
                $this->cc_email = implode(',', $data->cc_email);
                if ($data->cco_email) {
                    $this->cco_email = implode(',', $data->cco_email);
                } else {
                    $this->cco_email = '';
                }
                if ($this->optional_param('type', 'created', PARAM_TEXT)) {
                    $this->id = $data->id;
                    $this->timecreated = $data->timecreated;
                }
                $this->save();
                break;
        }
    }

    public function set_data_for_dynamic_submission(): void
    {
        // TODO: Implement set_data_for_dynamic_submission() method.
    }


    /**
     * Return url
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url
    {
        return new moodle_url('/admin/tool/tutor_follow/index.php', ['i' => 3]);
    }

    /**
     * Form elements
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function definition()
    {
        global $DB;
        $mform = $this->_form;
        //Modifiy the type, when the petition is origin in js
        if ($this->optional_param('type', 'created', PARAM_TEXT)) {
            $this->type = self::TYPES[$this->optional_param('type', 'created', PARAM_TEXT)];
        }

        switch ($this->type) {
            case self::TYPES['created']:
                $this->get_field_for_edit_created($mform);
            case self::TYPES['show']:
                break;
            case self::TYPES['filter']:
                break;
            case self::TYPES['edit']:
                $recordid = $this->optional_param('id', 0, PARAM_TEXT);
                $record = $DB->get_record('tool_tutor_follow_report', array('id' => $recordid));
                $record->cco_email = !empty($record->cco_email) ? explode(',', $record->cco_email) : null;
                $this->get_field_for_edit_created($mform, ccochoices: $record->cco_email,
                    ccchoices: explode(',', $record->cc_email), id: $recordid,
                    timecreated: $record->timecreated);
                $this->set_data([
                    'status' => $record->status,
                    'authorid' => $record->authorid,
                    'title' => $record->title,
                ]);
                break;
        }
    }

    /**
     * Get field for edit and created
     *
     * @param $mform
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_field_for_edit_created($mform, $ccochoices = null, $ccchoices = null, $id = null, $timecreated = null)
    {
        $mform->addElement('autocomplete', 'status', get_string('status', 'tool_tutor_follow'), $this->statusoptions, [
            'multiple' => false,
            'noselectionstring' => get_string('choosedots'),
        ]);
        $mform->setDefault('status', 0);
        $mform->setType('status', PARAM_INT);

        $data = json_decode(tool_tutor_follow_get_data('json_user_data', 'data_user'));
        $values = [];
        foreach ($data->users as $user) {
            $values[$user->id] = $user->firstname . " " . $user->lastname . " " . $user->idnumber . " " . $user->email;
        }
        $values[0] = "";
        $mform->addElement('autocomplete', 'authorid', get_string('author', 'tool_tutor_follow'), $values, [
            'multiple' => false,
            'noselectionstring' => get_string('choosedots'),
        ]);
        $mform->setDefault('authorid', 0);
        $mform->setType('authorid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('title', 'tool_tutor_follow'), 'size="64"');
        $mform->setType('title', PARAM_TEXT);

        $mform->addElement('editor', 'description', get_string('description', 'tool_tutor_follow'), null, [
            'maxfiles' => 0,
            'maxbytes' => 0,
            'context' => \context_system::instance(),
        ]);
        $mform->setType('description', PARAM_RAW);

        $attributes = [
            'multiple' => true,
            'ajax' => 'core_user/form_user_selector',
            'noselectionstring' => get_string('seachuser', 'tool_tutor_follow'),
            'valuehtmlcallback' => function ($userid) {
                global $DB, $OUTPUT;
                $context = \context_system::instance();
                $fields = \core_user\fields::for_name()->with_identity($context, false);
                $record = \core_user::get_user($userid, 'id' . $fields->get_sql()->selects, MUST_EXIST);
                $user = (object)[
                    'id' => $record->id,
                    'fullname' => fullname($record, has_capability('moodle/site:viewfullnames', $context)),
                    'extrafields' => [],
                ];
                foreach ($fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]) as $extrafield) {
                    $user->extrafields[] = (object)[
                        'name' => $extrafield,
                        'value' => s($record->$extrafield)
                    ];
                }
                return $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user);
            },
        ];

        $mform->addElement('autocomplete', 'cc_email', get_string('cc_email', 'tool_tutor_follow'), [], $attributes);
        $mform->setType('cc_email', PARAM_SEQUENCE);
        $mform->setDefault('cc_email', $ccchoices);

        $mform->addElement('autocomplete', 'cco_email', get_string('cco_email', 'tool_tutor_follow'), [], $attributes);
        $mform->setType('cco_email', PARAM_SEQUENCE);
        if ($ccchoices != null) {
            $mform->setDefault('cco_email', $ccochoices);
        }

        $mform->addRule('status', get_string('required'), 'required', null, 'client');
        $mform->addRule('title', get_string('required'), 'required', null, 'client');
        $mform->addRule('description', get_string('required'), 'required', null, 'client');
        $mform->addRule('title', get_string('required'), 'required', null, 'client');
        $mform->addRule('cc_email', get_string('required'), 'required', null, 'client');
        $mform->addRule('authorid', get_string('required'), 'required', null, 'client');

        if ($this->optional_param('id', 'created', PARAM_INT)) {
            $mform->addElement("text", 'id', get_string('id', 'core'));
            $mform->setDefault('id', $id);
            $mform->addElement("text", 'timecreated', get_string('timecreated', 'core'));
            $mform->setDefault('timecreated', $timecreated);
        }
    }

    /**
     * Save record
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function save()
    {
        global $DB;
        $record = $this->to_stdclass();
        $record->lasupdated = time();
        if ($this->optional_param('type', 'created', PARAM_TEXT)) {
            $record->id = $this->optional_param('id', null, PARAM_INT);
            $record->timecreated = $this->timecreated;
            $DB->update_record('tool_tutor_follow_report', $record);
        } else {
            unset($record->id);
            $record->timecreated = time();
            $DB->insert_record('tool_tutor_follow_report', $record);
        }
    }

    /**
     * Return stdclass
     *
     * @return \stdClass
     */
    public function to_stdclass(): \stdClass
    {
        return (object)[
            'id' => $this->id,
            'status' => $this->status,
            'authorid' => $this->authorid,
            'title' => $this->title,
            'description' => $this->description,
            'cc_email' => $this->cc_email,
            'cco_email' => $this->cco_email,
            'timecreated' => $this->timecreated,
        ];
    }
}