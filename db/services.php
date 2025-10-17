<?php


$functions = [
    'tool_tutor_follow_get_data_course_for_user' => [
        'classname' => 'tool_tutor_follow\external\get_external_api',
        'methodname' => 'get_data_courses',
        'description' => 'Traer la data de los cursos, de un usuario',
        'type' => 'write',
        'ajax' => true,
        'classpath' => 'admin/tool/tutor_follow/classes/external/get_external_api.php',
        'capabilities' => 'tool/tutor_follow:view'
    ]
];
