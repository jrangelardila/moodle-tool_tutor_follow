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
 * Handles the dashboard interactions for tutor follow-up data.
 *
 * @module     tool_tutor_follow/reports
 * @package    tool_tutor_follow
 * @copyright  2025 Jhon Rangel Ardila
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    "jquery",
    'core/str',
    'core/notification',
    'core_form/modalform'
], function ($, Str, Notification, ModalForm) {

    return {
        add_report: async function () {
            //Add report
            const title = await Str.get_string('add_report', 'tool_tutor_follow');
            $("#add_report").on("click", function (e) {
                e.preventDefault();
                const form = new ModalForm({
                    formClass: 'tool_tutor_follow\\local\\report_teacher',
                    modalConfig: {
                        title: `<h3 class="text-primary">${title}</h3>`,
                    },
                    args: {
                        type: 'created'
                    }
                });
                form.addEventListener(form.events.FORM_SUBMITTED, async function () {
                    location.reload();
                });
                form.show();
            });
            //Edit report
            $(".edit-report").on("click", async function (e) {
                e.preventDefault();
                const title = await Str.get_string('edit_report', 'tool_tutor_follow');
                const id = $(this).data('id');
                const form = new ModalForm({
                    formClass: 'tool_tutor_follow\\local\\report_teacher',
                    modalConfig: {
                        title: `<h3 class="text-primary">${title}</h3>`,
                    },
                    args: {
                        type: 'edit',
                        id: id,
                    }
                });

                form.addEventListener(form.events.FORM_SUBMITTED, async function () {
                    location.reload();
                });


                await form.show();
            });
            //Delete report
            $(".delete-report").on("click", async function (e) {
                e.preventDefault();
                const title = await Str.get_string('delete-report', 'tool_tutor_follow');
                const id = $(this).data('id');
                const form = new ModalForm({
                    formClass: 'tool_tutor_follow\\local\\report_teacher',
                    modalConfig: {
                        title: `<h3 class="text-primary">${title}</h3>`,
                    },
                    args: {
                        type: 'delete',
                        id: id,
                    }
                });
                const element = $(this);
                form.addEventListener(form.events.FORM_SUBMITTED, async function () {
                    element.closest('tr').fadeOut(200);
                });

                await form.show();
            });
        }
    };
});
