<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pricing Engine Version
    |--------------------------------------------------------------------------
    */
    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    */
    'default_currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Supported Plans & Base Daily Rates (in USD)
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'BASIC' => [
            'name' => 'Basic Protection',
            'base_daily_rate' => '10.00',
        ],
        'STANDARD' => [
            'name' => 'Standard Protection',
            'base_daily_rate' => '15.00',
        ],
        'PREMIUM' => [
            'name' => 'Premium Protection',
            'base_daily_rate' => '25.00',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Destinations & Multipliers
    |--------------------------------------------------------------------------
    */
    'destinations' => [
        'DOMESTIC' => [
            'name' => 'Domestic Travel',
            'multiplier' => '1.00',
        ],
        'ASIA' => [
            'name' => 'Asia Pacific',
            'multiplier' => '1.20',
        ],
        'EUROPE' => [
            'name' => 'Europe / Schengen Area',
            'multiplier' => '1.50',
        ],
        'WORLDWIDE' => [
            'name' => 'Worldwide (incl. US & Canada)',
            'multiplier' => '2.00',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Coverage Types & Multipliers
    |--------------------------------------------------------------------------
    */
    'coverages' => [
        'MEDICAL_ONLY' => [
            'name' => 'Medical Expenses Only',
            'multiplier' => '1.00',
        ],
        'STANDARD_COVER' => [
            'name' => 'Medical + Baggage Delay + Trip Cancellation',
            'multiplier' => '1.25',
        ],
        'COMPREHENSIVE' => [
            'name' => 'All Inclusive Comprehensive Cover',
            'multiplier' => '1.50',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Age Bands & Multipliers (Calculated at Trip Start Date)
    |--------------------------------------------------------------------------
    */
    'age_bands' => [
        '0_17' => [
            'min_age' => 0,
            'max_age' => 17,
            'label' => '0-17 (Child)',
            'multiplier' => '0.80',
        ],
        '18_35' => [
            'min_age' => 18,
            'max_age' => 35,
            'label' => '18-35 (Young Adult)',
            'multiplier' => '1.00',
        ],
        '36_50' => [
            'min_age' => 36,
            'max_age' => 50,
            'label' => '36-50 (Adult)',
            'multiplier' => '1.20',
        ],
        '51_65' => [
            'min_age' => 51,
            'max_age' => 65,
            'label' => '51-65 (Senior)',
            'multiplier' => '1.50',
        ],
        '66_75' => [
            'min_age' => 66,
            'max_age' => 75,
            'label' => '66-75 (Elderly)',
            'multiplier' => '2.00',
        ],
        '76_plus' => [
            'min_age' => 76,
            'max_age' => 120,
            'label' => '76+ (Super Senior)',
            'multiplier' => '2.50',
        ],
    ],
];
