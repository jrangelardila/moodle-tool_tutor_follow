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

namespace tool_tutor_follow\form;

use cache;
use core_course_category;

class filter_user_data extends \moodleform
{
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '',
                                $attributes = null, $editable = true, $ajaxformdata = null, $elements = null)
    {
        $this->elements = $elements;
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $ajaxformdata);
    }

    private $elements = [];

    /**
     * @inheritDoc
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function definition()
    {
        $mform = $this->_form;

        $data = json_decode(tool_tutor_follow_get_data('json_user_data', 'data_user'));
        $values = [];
        foreach ($data->users as $user) {
            $values[$user->id] = $user->firstname . " " . $user->lastname . " " . $user->idnumber . " " . $user->email;
        }

        $categories_config = json_decode(get_config('tool_tutor_follow', 'categories'));
        $values_categories = [];
        foreach ($categories_config as $category) {
            try {
                $categoria = core_course_category::get($category);
                $fullpath = $categoria->get_nested_name(false, '/');
                $values_categories[$category] = $fullpath;
            } catch (\moodle_exception $e) {

            }
        }

        $mform->addElement('html', '<br><br>');

        if (in_array('category', $this->elements)) $mform->addElement('autocomplete', 'category', get_string('category', 'tool_tutor_follow'),
            $values_categories, ['multiple' => true]);

        if (in_array('user', $this->elements)) $mform->addElement('autocomplete', 'user', get_string('users', 'tool_tutor_follow'), $values, ['multiple' => true]);

        if (in_array('endtime', $this->elements)) {
            $mform->addElement(
                'date_time_selector',
                'endtime',
                get_string('endtime', 'tool_tutor_follow'),
                ['optional' => true]
            );
        }

        $filters_cache = cache::make('tool_tutor_follow', 'form_cache');

        try {
            $mform->setDefault("category", $filters_cache->get("filter_category"));
            $mform->setDefault("user", $filters_cache->get("filter_users"));
        } catch (\moodle_exception $e) {
            $mform->setDefault("category", null);
            $mform->setDefault("user", null);
        }

        $this->add_action_buttons();
    }
}