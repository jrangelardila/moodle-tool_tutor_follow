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
 * @module     tool_tutor_follow/chart
 * @package    tool_tutor_follow
 * @copyright  2025 Jhon Rangel Ardila
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/chart_builder', 'core/chart_output_chartjs'], function ($, Builder, Output) {
    return {
        print_chart: function (chartData, canvaid) {
            const chartArea = $(`#${canvaid}`);
            Builder.make(chartData).then(function (chartInstance) {
                new Output(chartArea, chartInstance);
            });
        },
        print_table_data: function (addelement, labels, data) {
            const tabla = $('<table class="table table-striped" border="1"></table>');
            const encabezado = $(`<thead>
        <tr class="text-center bg-primary text-white"></tr>
    </thead>`);

            labels.forEach(enc => {
                const th = $(`<th class="text-center">${enc}</th>`);
                encabezado.find('tr').append(th);
            });

            const cuerpo = $('<tbody></tbody>');

            const numRows = data[0].length;

            for (let i = 0; i < numRows; i++) {
                const fila = $('<tr></tr>');
                data.forEach(columna => {
                    const td = $(`<td class="text-center">${columna[i]}</td>`);
                    fila.append(td);
                });
                cuerpo.append(fila);
            }

            tabla.append(encabezado);
            tabla.append(cuerpo);

            $(addelement).append(`<br><hr>`);
            $(addelement).append(tabla);
        }
    };
});

