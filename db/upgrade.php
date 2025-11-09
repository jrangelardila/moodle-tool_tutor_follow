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
 * Plugin upgrade steps are defined here.
 *
 * @package     tool_tutor_follow
 * @category    upgrade
 * @copyright   2024 Jhon Rangel <jrangelardila@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute tool_tutor_follow upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 * @throws ddl_exception
 */
function xmldb_tool_tutor_follow_upgrade($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    // Define table tool_tutor_follow to be created.
    $table = new xmldb_table('tool_tutor_follow');
    // Conditionally launch create table for tool_tutor_follow.
    if (!$dbman->table_exists($table)) {
        // Adding fields to table tool_tutor_follow.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('instance_id', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_field('datajson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('lastupdate', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        // Adding keys to table tool_tutor_follow.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Created table
        $dbman->create_table($table);
    }
    // Define table tool_tutor_follow_report to be created.
    $table = new xmldb_table('tool_tutor_follow_report');
    // Conditionally launch create table for tool_tutor_follow_report.
    if (!$dbman->table_exists($table)) {
        // Adding fields to table tool_tutor_follow_report.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '20', null, null, null, '0');
        $table->add_field('authorid', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_field('title', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('cc_email', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('cco_email', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_field('lasupdated', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        // Adding keys to table tool_tutor_follow_report.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Created table
        $dbman->create_table($table);
    }
    // Define table tool_tutor_follow_issue to be created.
    $table = new xmldb_table('tool_tutor_follow_issue');
    // Conditionally launch create table for tool_tutor_follow_issue.
    if (!$dbman->table_exists($table)) {
        // Adding fields to table tool_tutor_follow_issue.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reportorder', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('teacherid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '20', null, null, null, '0');
        $table->add_field('moduleid', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_field('issue', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('attach', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_field('lastupdate', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        // Adding keys to table tool_tutor_follow_issue.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    return true;
}
