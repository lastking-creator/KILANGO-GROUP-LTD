<?php

return [
    // The attendance device on your network
    'host' => env('HIK_HOST', '192.168.1.100'),
    'port' => env('HIK_PORT', 80),
    'username' => env('HIK_USERNAME', 'admin'),
    'password' => env('HIK_PASSWORD'),
    'timeout' => 10,

    // How punches become attendance
    'work_start' => env('ATTENDANCE_WORK_START', '08:00'),
    'late_after_minutes' => (int) env('ATTENDANCE_LATE_AFTER_MINUTES', 15),
    'min_checkout_gap_minutes' => 30,   // a later punch counts as check-out only if it is this long after check-in
];
