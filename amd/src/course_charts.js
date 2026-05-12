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
 * Course detail charts for tool_tutor_follow.
 *
 * @module     tool_tutor_follow/course_charts
 * @package
 * @copyright  2025 Jhon Rangel Ardila
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/chartjs'], function(Chart) {
    'use strict';

    /** @type {object} Shared color palette */
    var C = {
        blue:      'rgba(15,108,191,0.82)',
        green:     'rgba(25,170,100,0.82)',
        red:       'rgba(220,53,69,0.82)',
        orange:    'rgba(253,126,20,0.82)',
        blueSoft:  'rgba(15,108,191,0.18)',
        greenSoft: 'rgba(25,170,100,0.18)',
    };

    /**
     * Shared Chart.js default options for bar charts.
     *
     * @param {string} title Chart title text.
     * @returns {object} Chart.js options object.
     */
    function baseOptions(title) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {position: 'bottom', labels: {boxWidth: 12, padding: 16, font: {size: 12}}},
                title: {display: true, text: title, font: {size: 13, weight: 'bold'}, padding: {bottom: 12}},
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ' ' + ctx.dataset.label + ': ' + ctx.parsed.x;
                        }
                    }
                }
            }
        };
    }

    /**
     * Build an HTML table string from column headers and data rows.
     *
     * @param {Array<string>} headers Column header labels.
     * @param {Array<Array>}  rows    Array of row arrays.
     * @returns {string} HTML table markup.
     */
    function buildHtmlTable(headers, rows) {
        var html = '<table class="dtl-tbl" style="width:100%"><thead><tr>';
        headers.forEach(function(h) {
            html += '<th>' + h + '</th>';
        });
        html += '</tr></thead><tbody>';
        rows.forEach(function(row) {
            html += '<tr>';
            row.forEach(function(cell) {
                html += '<td>' + cell + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        return html;
    }

    /**
     * Hide a previously shown chart-data modal and clean up the backdrop.
     *
     * @param {string} modalId The base ID of the modal element.
     * @returns {void}
     */
    function hideModal(modalId) {
        var el = document.getElementById(modalId);
        if (el) {
            el.classList.remove('show');
            el.style.display = 'none';
        }
        document.body.classList.remove('modal-open');
        var bd = document.querySelector('.modal-backdrop[data-for="' + modalId + '"]');
        if (bd) {
            bd.parentNode.removeChild(bd);
        }
    }

    /**
     * Populate and show a Bootstrap-style modal with the given HTML content.
     *
     * @param {string} modalId The base ID of the modal element.
     * @param {string} title   Modal title text.
     * @param {string} html    HTML to inject into the modal body.
     * @returns {void}
     */
    function showModal(modalId, title, html) {
        var el      = document.getElementById(modalId);
        var titleEl = document.getElementById(modalId + '-title');
        var bodyEl  = document.getElementById(modalId + '-body');
        if (!el || !titleEl || !bodyEl) {
            return;
        }
        titleEl.textContent = title;
        bodyEl.innerHTML = html;
        el.style.display = 'block';
        window.requestAnimationFrame(function() {
            el.classList.add('show');
        });
        var bd = document.createElement('div');
        bd.className = 'modal-backdrop fade show';
        bd.setAttribute('data-for', modalId);
        document.body.appendChild(bd);
        document.body.classList.add('modal-open');
        bd.addEventListener('click', function() {hideModal(modalId);});
    }

    /**
     * Build horizontal grouped bar: submitted / graded / pending per activity.
     *
     * @param {HTMLCanvasElement} canvas
     * @param {object} data
     * @returns {void}
     */
    function buildActivityBar(canvas, data) {
        var labels          = [];
        var submitted       = [];
        var graded          = [];
        var pending         = [];
        var withoutFeedback = [];

        data.forums.forEach(function(f) {
            labels.push('🗣 ' + f.name);
            submitted.push(f.participated);
            graded.push(f.graded);
            pending.push(f.pending);
            withoutFeedback.push(f.without_feedback || 0);
        });
        data.assigns.forEach(function(a) {
            labels.push('📄 ' + a.name);
            submitted.push(a.submitted);
            graded.push(a.graded);
            pending.push(a.pending);
            withoutFeedback.push(a.without_feedback || 0);
        });

        var opts = baseOptions(data.str_activity_status);
        opts.indexAxis = 'y';
        opts.scales = {
            x: {stacked: false, beginAtZero: true, grid: {color: 'rgba(0,0,0,.05)'}},
            y: {stacked: false, ticks: {font: {size: 11}}}
        };

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {label: data.str_submitted,        data: submitted,       backgroundColor: C.blue,   borderRadius: 4},
                    {label: data.str_graded,           data: graded,          backgroundColor: C.green,  borderRadius: 4},
                    {label: data.str_pending,          data: pending,         backgroundColor: C.red,    borderRadius: 4},
                    {label: data.str_without_feedback, data: withoutFeedback, backgroundColor: C.orange, borderRadius: 4}
                ]
            },
            options: opts
        });
    }

    /**
     * Build doughnut: graded vs pending total.
     *
     * @param {HTMLCanvasElement} canvas
     * @param {object} data
     * @returns {void}
     */
    function buildCoverageDonut(canvas, data) {
        var pct = data.total_graded + data.total_pending > 0
            ? Math.round(data.total_graded / (data.total_graded + data.total_pending) * 100)
            : 0;

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: [data.str_graded, data.str_pending],
                datasets: [{
                    data: [data.total_graded, data.total_pending],
                    backgroundColor: [C.green, C.red],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {position: 'bottom', labels: {boxWidth: 12, padding: 14}},
                    title: {display: true, text: data.str_coverage + ' (' + pct + '%)', font: {size: 13, weight: 'bold'}}
                }
            }
        });
    }

    /**
     * Build line chart: cumulative submissions and grades timeline.
     *
     * @param {HTMLCanvasElement} canvas
     * @param {object} data
     * @returns {void}
     */
    function buildTimeline(canvas, data) {
        var points = data.timeline || [];
        if (!points.length) {
            canvas.parentElement.style.display = 'none';
            return;
        }

        points.sort(function(a, b) {return a.ts - b.ts;});

        var labels   = [];
        var cumSub   = [];
        var cumGrade = [];
        var cumWF    = [];
        var runSub   = 0;
        var runGrade = 0;
        var runWF    = 0;

        points.forEach(function(p) {
            labels.push(p.label);
            runSub   += p.submissions       || 0;
            runGrade += p.grades            || 0;
            runWF    += p.without_feedback  || 0;
            cumSub.push(runSub);
            cumGrade.push(runGrade);
            cumWF.push(runWF);
        });

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: data.str_submissions_acc,
                        data: cumSub,
                        borderColor: C.blue,
                        backgroundColor: C.blueSoft,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3
                    },
                    {
                        label: data.str_grades_acc,
                        data: cumGrade,
                        borderColor: C.green,
                        backgroundColor: C.greenSoft,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3
                    },
                    {
                        label: data.str_without_feedback,
                        data: cumWF,
                        borderColor: C.orange,
                        backgroundColor: 'rgba(253,126,20,0.12)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {position: 'bottom'},
                    title: {display: true, text: data.str_timeline, font: {size: 13, weight: 'bold'}}
                },
                scales: {
                    x: {ticks: {maxRotation: 45, font: {size: 10}}, grid: {display: false}},
                    y: {beginAtZero: true, grid: {color: 'rgba(0,0,0,.05)'}}
                }
            }
        });
    }

    /**
     * Wire up "Ver tabla" buttons to open the data modal for course charts.
     *
     * @param {number} courseid
     * @param {object} data
     * @returns {void}
     */
    function initModal(courseid, data) {
        var modalId = 'dtl-cmodal-' + courseid;
        var el = document.getElementById(modalId);
        if (!el) {
            return;
        }

        var closeBtn = el.querySelector('[data-bs-dismiss="modal"]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {hideModal(modalId);});
        }

        document.querySelectorAll('[data-modal="' + modalId + '"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var chartType = btn.getAttribute('data-chart');
                var title = '';
                var html  = '';

                if (chartType === 'activity') {
                    title = data.str_activity_status;
                    var rows = [];
                    var num  = 1;
                    data.forums.forEach(function(f) {
                        rows.push([num++, f.name, data.str_forum_type,
                                   f.participated, f.graded, f.pending, f.without_feedback || 0]);
                    });
                    data.assigns.forEach(function(a) {
                        rows.push([num++, a.name, data.str_assign_type,
                                   a.submitted, a.graded, a.pending, a.without_feedback || 0]);
                    });
                    html = buildHtmlTable(
                        ['#', data.str_nameactivity, data.str_type,
                         data.str_submitted, data.str_graded, data.str_pending, data.str_without_feedback],
                        rows
                    );
                } else if (chartType === 'timeline') {
                    title = data.str_timeline;
                    html = buildHtmlTable(
                        [data.str_startdate, data.str_submitted, data.str_graded, data.str_without_feedback],
                        (data.timeline || []).map(function(p) {
                            return [p.label, p.submissions, p.grades, p.without_feedback || 0];
                        })
                    );
                }

                if (html) {
                    showModal(modalId, title, html);
                }
            });
        });
    }

    /**
     * Initialize course detail charts.
     *
     * @param {number} courseid
     * @returns {void}
     */
    function init(courseid) {
        var dataEl = document.getElementById('dtl-course-data-' + courseid);
        if (!dataEl) {
            return;
        }
        var data = JSON.parse(dataEl.textContent);

        var actCanvas  = document.getElementById('dtl-act-chart-' + courseid);
        var covCanvas  = document.getElementById('dtl-cov-chart-' + courseid);
        var timeCanvas = document.getElementById('dtl-time-chart-' + courseid);

        if (actCanvas) {
            buildActivityBar(actCanvas, data);
        }
        if (covCanvas) {
            buildCoverageDonut(covCanvas, data);
        }
        if (timeCanvas) {
            buildTimeline(timeCanvas, data);
        }

        initModal(courseid, data);
    }

    return {
        init: init
    };
});
