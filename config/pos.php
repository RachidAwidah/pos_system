<?php

return [
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'admin_password' => env('ADMIN_PASSWORD', 'ChangeMe!123'),
    'seed_demo_data' => (bool) env('POS_SEED_DEMO_DATA', false),
];
