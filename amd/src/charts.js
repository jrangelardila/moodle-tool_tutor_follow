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

