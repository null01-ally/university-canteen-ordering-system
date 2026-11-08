<?php

return [
    'require_account_approval' => (bool) env('CANTEEN_REQUIRE_ACCOUNT_APPROVAL', false),
    'registration_domains' => array_values(array_filter(array_map('trim', explode(',', strtolower((string) env('CANTEEN_REGISTRATION_DOMAINS', '')))))),
    'low_stock_threshold' => max(1, (int) env('CANTEEN_LOW_STOCK_THRESHOLD', 5)),
    'campus_delivery_locations' => array_values(array_filter(array_map('trim', explode('|', (string) env('CANTEEN_CAMPUS_LOCATIONS', ''))))),
    'timezone' => 'Asia/Manila',
];
