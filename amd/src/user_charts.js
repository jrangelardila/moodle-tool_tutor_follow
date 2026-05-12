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
 * User detail charts for tool_tutor_follow.
 *
 * @module     tool_tutor_follow/user_charts
 * @package
 * @copyright  2025 Jhon Rangel Ardila
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/chartjs'], function(Chart) {
    'use strict';

    /** @type {Array} Color sequence for multi-course radar */
    var PALETTE = [
        'rgba(15,108,191,0.7)',
        'rgba(25,170,100,0.7)',
        'rgba(220,53,69,0.7)',
        'rgba(255,152,0,0.7)',
        'rgba(111,66,193,0.7)',
        'rgba(0,188,212,0.7)',
    ];

    /**
     * Normalize an array of numbers to 0-100 scale.
     *
     * @param {Array<number>} arr
     * @returns {Array<number>}
     */
    function normalize(arr) {
        var max = Math.max.apply(null, arr);
        if (max === 0) {
            return arr.map(function() {return 0;});
        }
        return arr.map(function(v) {return Math.round(v / max * 100);});
    }

    /**
     * Build a "shortname – fullname" display label for a course.
     *
     * @param {object} c Course object with shortname and fullname properties.
     * @returns {string}
     */
    function courseLabel(c) {
        return c.shortname + ' – ' + c.fullname;
    }

    /**
     * Build an HTML table string from column headers and data rows.
     *
     * @param {Array<string>} headers Column header labels.
     * @param {Array<Array>}  rows    Array of row arrays (each cell is a string or number).
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
     * Build horizontal grouped bar: students / graded / pending per course.
     *
     * @param {HTMLCanvasElement} canvas
     * @param {object} data
     * @returns {void}
     */
    function buildLoadBar(canvas, data) {
        var labels          = data.courses.map(courseLabel);
        var graded          = data.courses.map(function(c) {return c.graded;});
        var pending         = data.courses.map(function(c) {return c.pending;});
        var students        = data.courses.map(function(c) {return c.students;});
        var withoutFeedback = data.courses.map(function(c) {return c.without_feedback || 0;});

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {label: data.str_students,
                        data: students, backgroundColor: 'rgba(15,108,191,0.7)', borderRadius: 4},
                    {label: data.str_graded,
                        data: graded, backgroundColor: 'rgba(25,170,100,0.7)', borderRadius: 4},
                    {label: data.str_pending,
                        data: pending, backgroundColor: 'rgba(220,53,69,0.7)', borderRadius: 4},
                    {label: data.str_without_feedback,
                        data: withoutFeedback, backgroundColor: 'rgba(253,126,20,0.82)', borderRadius: 4}
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {position: 'bottom', labels: {boxWidth: 12, padding: 14}},
                    title: {display: true, text: data.str_load_title, font: {size: 13, weight: 'bold'}}
                },
                scales: {
                    x: {beginAtZero: true, grid: {color: 'rgba(0,0,0,.05)'}},
                    y: {ticks: {font: {size: 11}}}
                }
            }
        });
    }

    /**
     * Build doughnut: forums vs assigns composition.
     *
     * @param {HTMLCanvasElement} canvas
     * @param {object} data
     * @returns {void}
     */
    function buildCompositionDonut(canvas, data) {
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: [data.str_forums, data.str_assigns],
                datasets: [{
                    data: [data.total_forums, data.total_assigns],
                    backgroundColor: ['rgba(15,108,191,0.8)', 'rgba(111,66,193,0.8)'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {position: 'bottom', labels: {boxWidth: 12, padding: 14}},
                    title: {display: true, text: data.str_composition_title, font: {size: 13, weight: 'bold'}}
                }
            }
        });
    }

    /**
     * Build radar: academic profile per course (normalized metrics).
     * Axes: % graded, students, connection minutes, activities.
     *
     * @param {HTMLCanvasElement} canvas
     * @param {object} data
     * @returns {void}
     */
    function buildRadar(canvas, data) {
        var courses = data.courses;
        if (!courses.length) {
            canvas.parentElement.style.display = 'none';
            return;
        }

        var pctGraded  = courses.map(function(c) {
            var total = c.graded + c.pending;
            return total > 0 ? Math.round(c.graded / total * 100) : 0;
        });
        var normStudents   = normalize(courses.map(function(c) {return c.students;}));
        var normMinutes    = normalize(courses.map(function(c) {return c.connection_minutes;}));
        var normActivities = normalize(courses.map(function(c) {return c.forums + c.assigns;}));

        var labels = [data.str_pct_graded, data.str_students, data.str_connection, data.str_activities];

        var datasets = courses.map(function(c, i) {
            var color = PALETTE[i % PALETTE.length];
            return {
                label: courseLabel(c),
                data: [pctGraded[i], normStudents[i], normMinutes[i], normActivities[i]],
                backgroundColor: color.replace('0.7', '0.12'),
                borderColor: color.replace('0.7', '1'),
                borderWidth: 2,
                pointBackgroundColor: color.replace('0.7', '1'),
                pointRadius: 4
            };
        });

        new Chart(canvas, {
            type: 'radar',
            data: {labels: labels, datasets: datasets},
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {position: 'bottom', labels: {boxWidth: 10, padding: 12, font: {size: 11}}},
                    title: {display: true, text: data.str_radar_title, font: {size: 13, weight: 'bold'}}
                },
                scales: {
                    r: {
                        min: 0,
                        max: 100,
                        ticks: {stepSize: 25, font: {size: 9}, backdropColor: 'transparent'},
                        grid: {color: 'rgba(0,0,0,.08)'},
                        pointLabels: {font: {size: 11}}
                    }
                }
            }
        });
    }

    /**
     * Wire up "Ver tabla" buttons to open the data modal for user charts.
     *
     * @param {number} userid
     * @param {object} data
     * @returns {void}
     */
    function initModal(userid, data) {
        var modalId = 'dtl-umodal-' + userid;
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

                if (chartType === 'load') {
                    title = data.str_load_title;
                    html = buildHtmlTable(
                        ['#', data.str_course, data.str_students,
                         data.str_graded, data.str_pending, data.str_without_feedback, '%'],
                        data.courses.map(function(c, i) {
                            var t = c.graded + c.pending;
                            var pct = t > 0 ? Math.round(c.graded / t * 100) : 0;
                            return [i + 1, courseLabel(c), c.students, c.graded, c.pending, c.without_feedback || 0, pct + '%'];
                        })
                    );
                } else if (chartType === 'radar') {
                    title = data.str_radar_title;
                    html = buildHtmlTable(
                        ['#', data.str_course, data.str_pct_graded, data.str_students,
                         data.str_connection + ' (min)', data.str_activities],
                        data.courses.map(function(c, i) {
                            var t = c.graded + c.pending;
                            var pct = t > 0 ? Math.round(c.graded / t * 100) : 0;
                            return [i + 1, courseLabel(c), pct + '%', c.students,
                                    c.connection_minutes, c.forums + c.assigns];
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
     * Initialize user detail charts.
     *
     * @param {number} userid
     * @returns {void}
     */
    function init(userid) {
        var dataEl = document.getElementById('dtl-user-data-' + userid);
        if (!dataEl) {
            return;
        }
        var data = JSON.parse(dataEl.textContent);

        var loadCanvas  = document.getElementById('dtl-load-chart-' + userid);
        var donutCanvas = document.getElementById('dtl-donut-chart-' + userid);
        var radarCanvas = document.getElementById('dtl-radar-chart-' + userid);

        if (loadCanvas) {
            buildLoadBar(loadCanvas, data);
        }
        if (donutCanvas) {
            buildCompositionDonut(donutCanvas, data);
        }
        if (radarCanvas) {
            buildRadar(radarCanvas, data);
        }

        initModal(userid, data);
    }

    return {
        init: init
    };
});
