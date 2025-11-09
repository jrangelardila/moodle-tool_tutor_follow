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

use context;
use core_form\dynamic_form;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class issue_teacher extends dynamic_form
{

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

    public function process_dynamic_submission()
    {
        // TODO: Implement process_dynamic_submission() method.
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
        return new moodle_url('/admin/tool/tutor_follow/index.php');
    }

    protected function definition()
    {
        // TODO: Implement definition() method.
    }
}