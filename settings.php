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
 * Plugin administration pages are defined here.
 *
 * @package     tool_tutor_follow
 * @category    admin
 * @copyright   2024 Jhon Rangel <jrangelardila@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    /**
     * Agregar un enlace en la categoría de "Usuarios" en el administrador.
     */
    $ADMIN->add('users', new admin_externalpage(
        'tool_tutor_follow',
        get_string('pluginname', 'tool_tutor_follow'),
        new moodle_url('/admin/tool/tutor_follow/index.php'),
        'tool/tutor_follow:view'
    ));

    //Settigs page
    $settings = new admin_settingpage('tool_tutor_follow_settings', get_string('settings_page', 'tool_tutor_follow'));
    //days for save data in reports
    $settings->add(new admin_setting_configtext(
        'tool_tutor_follow/reports_days',
        get_string('reports_days_label', 'tool_tutor_follow'),
        get_string('reports_days_desc', 'tool_tutor_follow'),
        21,
        PARAM_INT
    ));
    // days limit desconnection for report
    $settings->add(new admin_setting_configtext(
        'tool_tutor_follow/days_limit',
        get_string('dayslimit_label', 'tool_tutor_follow'),
        get_string('dayslimit_desc', 'tool_tutor_follow'),
        8,
        PARAM_INT
    ));
    // Limit in days for grading teacher
    $settings->add(new admin_setting_configtext(
        'tool_tutor_follow/days_limit_grading',
        get_string('daysgrading_label', 'tool_tutor_follow'),
        get_string('daysgrading_desc', 'tool_tutor_follow'),
        8,
        PARAM_INT
    ));
    $ADMIN->add('users', $settings);
}
