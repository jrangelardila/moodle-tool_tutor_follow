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

namespace tool_tutor_follow;

use core_course_category;

require_once($CFG->libdir . '/formslib.php');


class form_configuration extends \moodleform
{
    /**
     * @inheritDoc
     */
    protected function definition()
    {
        $mform = $this->_form;

        $mform->addElement('html',
            '<br><h3 class="text-primary">' .
            get_string('selectcategories', 'tool_tutor_follow') .
            '</h3>'
        );

        $mform->addElement('autocomplete', 'categories',
            get_string('categoriesanalyze', 'tool_tutor_follow'),
            core_course_category::make_categories_list(),
            ['multiple' => true]
        );
        $mform->setDefault('categories', json_decode(get_config('tool_tutor_follow', 'categories')));

        $mform->addElement('html',
            '<br><h3 class="text-primary">' .
            get_string('rolesanalyzed', 'tool_tutor_follow') .
            '</h3>'
        );

        $roles = role_get_names();
        $roleOptions = [];
        foreach ($roles as $role) {
            if (stripos($role->shortname, 'teacher') !== false) {
                $roleOptions[$role->shortname] = $role->name;
            }
        }

        $mform->addElement('autocomplete', 'roles',
            get_string('roleselect', 'tool_tutor_follow'),
            $roleOptions,
            ['multiple' => true]
        );
        $mform->setDefault('roles', json_decode(get_config('tool_tutor_follow', 'roles')));

        $mform->addElement('submit', 'submitbutton', get_string('savechanges'));
    }

    /**
     * Save the configurations
     *
     * @return void
     */
    public function update_configurations()
    {
        $categories = $this->get_data()->categories;
        $roles = $this->get_data()->roles;

        set_config('categories', json_encode($categories), 'tool_tutor_follow');
        set_config('roles', json_encode($roles), 'tool_tutor_follow');
    }
}