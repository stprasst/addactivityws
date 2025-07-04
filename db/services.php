<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_addactivityws_add_activity' => [
        'classname'   => 'local_addactivityws_external',
        'methodname'  => 'add_activity',
        'classpath'   => 'local/addactivityws/externallib.php',
        'description' => 'Add assignment or url activity to a course section.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'moodle/course:manageactivities'
    ],
    'local_addactivityws_get_course_sections' => [
        'classname'   => 'local_addactivityws_external',
        'methodname'  => 'get_course_sections',
        'classpath'   => 'local/addactivityws/externallib.php',
        'description' => 'Get course sections list',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'=> 'moodle/course:view'
    ],
];

$services = [
    'Add Activity Service' => [
        'functions' => ['local_addactivityws_add_activity','local_addactivityws_get_course_sections'],
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];