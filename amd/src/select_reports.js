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
 * Bulk selection of reports in option 3 list.
 *
 * @module     tool_tutor_follow/select_reports
 * @package    tool_tutor_follow
 * @copyright  2026 Jhon Rangel Ardila
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/str',
    'core/notification'
], function ($, Str, Notification) {

    return {
        init: function () {
            const form = document.getElementById('tool-tutor-follow-send-selected-form');
            if (!form) {
                return;
            }

            const sendBtn = form.querySelector('#tool-tutor-follow-send-selected');
            const deleteBtn = form.querySelector('#tool-tutor-follow-delete-selected');
            const master = form.querySelector('.select-all-reports');

            const refreshButtons = function () {
                const anyChecked = form.querySelectorAll('input.select-report:checked').length > 0;
                if (sendBtn) {
                    sendBtn.style.display = anyChecked ? '' : 'none';
                }
                if (deleteBtn) {
                    deleteBtn.style.display = anyChecked ? '' : 'none';
                }
            };

            if (master) {
                master.addEventListener('change', function () {
                    form.querySelectorAll('input.select-report').forEach(function (cb) {
                        cb.checked = master.checked;
                    });
                    refreshButtons();
                });
            }

            form.querySelectorAll('input.select-report').forEach(function (cb) {
                cb.addEventListener('change', refreshButtons);
            });

            if (deleteBtn) {
                deleteBtn.addEventListener('click', async function (e) {
                    if (deleteBtn.dataset.confirmed === '1') {
                        return;
                    }
                    e.preventDefault();

                    const checked = form.querySelectorAll('input.select-report:checked').length;
                    if (checked === 0) {
                        return;
                    }

                    const strings = await Str.get_strings([
                        {key: 'delete_reports_confirm_title', component: 'tool_tutor_follow'},
                        {key: 'delete_reports_confirm', component: 'tool_tutor_follow', param: checked},
                        {key: 'yes', component: 'moodle'},
                        {key: 'no', component: 'moodle'},
                    ]);

                    Notification.confirm(
                        strings[0],
                        strings[1],
                        strings[2],
                        strings[3],
                        function () {
                            deleteBtn.dataset.confirmed = '1';
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit(deleteBtn);
                            } else {
                                deleteBtn.click();
                            }
                        }
                    );
                });
            }

            refreshButtons();
        }
    };
});
