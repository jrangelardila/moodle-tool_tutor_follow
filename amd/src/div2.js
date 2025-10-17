define([
    "jquery",
    'core/ajax',
    'core/notification',
    'tool_tutor_follow/charts',
    'core/str',
    'core/yui',
    'core_form/modalform',
], function ($, ajax, notification, chartModule, Str, Y, ModalForm) {
    return {
        init: function () {
            $(".more-info").click(async function (event) {
                const number = $(event.currentTarget).attr("idnumber");

                const options = {
                    methodname: 'tool_tutor_follow_get_data_course_for_user',
                    args: {
                        idnumber: number
                    },
                };

                const response = await ajax.call([options])[0];
                const userObject = JSON.parse(response);
                this.executeStudentGraph(userObject, number);

            }.bind(this));
        },

        executeStudentGraph: async function (obj, idnumber) {
            $(".data-body").empty();

            const strings = [
                { key: 'close', component: 'tool_tutor_follow' },
                { key: 'distributionstudents', component: 'tool_tutor_follow' },
                { key: 'number', component: 'tool_tutor_follow' },
                { key: 'shortname', component: 'tool_tutor_follow' },
                { key: 'course', component: 'tool_tutor_follow' },
                { key: 'studentscount', component: 'tool_tutor_follow' },
                { key: 'students', component: 'tool_tutor_follow' }
            ];

            const results = await Str.get_strings(strings);
            const [
                closeText,
                distributionText,
                numberText,
                shortnameText,
                courseText,
                studentsCountText,
                studentsText
            ] = results;

            $(".data-body").append(`
                <div class="w-100 align-items-end">
                    <div type="button" class="btn btn-danger btn-close-students">
                        <i class="fas fa-times"></i> ${closeText}
                    </div>
                </div>`);

            let chart = $(`
                <h4 class="text-primary">${distributionText} ${obj.firstname} ${obj.lastname}</h4>
                <div class="container">
                    <canvas id="firstchart"></canvas>
                </div>`);
            $(".data-body").append(chart);

            const fullnames = obj.cursos.map(course => course.fullname);
            const students = obj.cursos.map(course => course.students);
            const shortnames = obj.cursos.map(course => course.shortname);

            const chartData = {
                type: 'pie',
                series: [{
                    label: studentsText,
                    labels: fullnames,
                    values: students,
                    colors: [],
                    axes: {x: null, y: null},
                    smooth: null
                }],
                labels: fullnames,
                title: `${obj.firstname} ${obj.lastname}`,
                axes: {x: [], y: []},
                config_colorset: null,
                doughnut: null
            };


            chartModule.print_chart(chartData, "firstchart");

            const numeros = Array.from({length: fullnames.length}, (v, k) => k + 1);
            chartModule.print_table_data(".data-body",
                [numberText, shortnameText, courseText, studentsCountText],
                [numeros, shortnames, fullnames, students]);

            $(".btn-close-students").click(function (event) {
                this.calification_btn(event);
            }.bind(this));
        },

        calification_btn: function (e) {
            $(".data-body").fadeOut(400, function () {
                $(this).empty().fadeIn(400);
            });
        }
    };
});

