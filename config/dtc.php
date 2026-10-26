<?php

return [
    // Shared secret the WordPress connector sends in the X-DTC-Key header.
    'api_key' => env('DTC_API_KEY'),

    'brand_name' => env('DTC_BRAND_NAME', 'David Tyre Company'),

    'timezone' => env('DTC_TIMEZONE', 'Asia/Manila'),

    // When true, a registration's DTC Code must be generated and unused.
    'require_dtc_code' => (bool) env('DTC_REQUIRE_DTC_CODE', false),

];
