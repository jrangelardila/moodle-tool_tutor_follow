<?php


defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'tool_tutor_follow\task\data_user_tutor',
        'blocking' => 0,
        'minute' => '*/1',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    ]
];
