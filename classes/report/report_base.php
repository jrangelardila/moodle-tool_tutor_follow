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
 * @package   tool_tutor_follow
 * @copyright 2026, author_fullname <author_link>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tutor_follow\report;

defined('MOODLE_INTERNAL') || die();

class report_base
{
    /**
     * report name
     * @var
     */
    protected $name;

    /**
     * report description
     *
     * @var
     */
    protected $description;

    /**
     * status of report
     *
     * @var [type]
     */
    protected $enable;
    public function __construct() {}

    /**
     * Get the report name.
     *
     * @return string
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Get the report description.
     *
     * @return string
     */
    public function get_description(): string
    {
        return $this->description;
    }

    /**
     * Get the report enable status.
     *
     * @return bool
     */
    public function get_enable(): bool
    {
        return $this->enable;
    }

    /**
     * Set the report enable status.
     *
     * @param bool $enable
     * @return void
     */
    public function set_enable(bool $enable): void
    {
        $this->enable = $enable;
    }

    /**
     *  Return view for report
     * 
     * @param mixed $id
     */
    public function print_report($id)
    {
        global $DB, $OUTPUT;

        $element = $DB->get_record('tool_tutor_follow_report', ['id' => $id], '*', MUST_EXIST);
        $autor = $DB->get_record('user', array('id' => $element->authorid));
        $context = \context_system::instance();
        $element->description = file_rewrite_pluginfile_urls(
            $element->description,
            'pluginfile.php',
            $context->id,
            'tool_tutor_follow',
            'description',
            $element->id
        );
        $get_users_data = function ($idstring) use ($DB, $OUTPUT) {
            if (empty($idstring)) return [];
            $ids = explode(',', $idstring);
            list($insql, $params) = $DB->get_in_or_equal($ids);
            $users = $DB->get_records_select('user', "id $insql", $params, '', 'id, firstname, lastname, picture, imagealt');
            $result = [];
            foreach ($users as $u) {
                $result[] = [
                    'fullname' => fullname($u),
                    'profileurl' => (new \moodle_url('/user/view.php', ['id' => $u->id]))->out(false),
                    'avatar' => $OUTPUT->user_picture($u, array('size' => 45))
                ];
            }
            return $result;
        };
        $data = [
            'id' => $element->id,
            'title' => $element->title,
            'description' => format_text($element->description, FORMAT_HTML),
            'status' => $element->status,
            'timecreated' => userdate($element->timecreated),
            'lastupdated' => userdate($element->lasupdated),
            'author' => [
                'fullname' => fullname($autor),
                'profileurl' => (new \moodle_url('/user/view.php', ['id' => $autor->id]))->out(false),
                'avatar' => $OUTPUT->user_picture($autor, array('size' => 60))
            ],
            'cc_users' => $get_users_data($element->cc_email),
            'cco_users' => $get_users_data($element->cco_email)
        ];
        echo $OUTPUT->render_from_template('tool_tutor_follow/report', $data);
    }
}
