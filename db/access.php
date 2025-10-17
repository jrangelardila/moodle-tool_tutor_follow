<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'tool/tutor_follow:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,  // El contexto se aplica a nivel de sistema.
        'archetypes' => [
            'manager' => CAP_ALLOW,  // Solo los Managers tienen acceso.
        ],
    ],
];

