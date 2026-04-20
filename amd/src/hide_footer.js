/**
 * Hide page footer on the reports list page so the table's natural
 * horizontal scrollbar (window scrollbar, since the table is positioned
 * absolute) stays reachable at the bottom of the viewport.
 *
 * @module     tool_tutor_follow/hide_footer
 * @package    tool_tutor_follow
 * @copyright  2026 Jhon Rangel Ardila
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        init: function() {
            var run = function() {
                var footer = document.getElementById('page-footer');
                if (footer) {
                    footer.style.display = 'none';
                }

                var form = document.getElementById('tool-tutor-follow-send-selected-form');
                if (form) {
                    var pagings = form.querySelectorAll('.paging, nav.pagination');
                    if (pagings.length > 1) {
                        pagings[pagings.length - 1].style.display = 'none';
                    }
                }
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run);
            } else {
                run();
            }
        }
    };
});
