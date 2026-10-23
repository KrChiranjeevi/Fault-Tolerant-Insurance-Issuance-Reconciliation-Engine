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
    'default_currency' => 'INR',

    /*
    |--------------------------------------------------------------------------
    | Supported Plans & Base Daily Rates
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'BASIC' => [
            'name' => 'Basic Protection',
            'base_daily_rate' => '12.00',
        ],
        'PLUS' => [
            'name' => 'Plus Protection',
            'base_daily_rate' => '20.00',
        ],
        'PREMIUM' => [
            'name' => 'Premium Protection',
            'base_daily_rate' => '30.00',
        ],
        // Retained for legacy test compatibility
        'STANDARD' => [
            'name' => 'Standard Protection',
            'base_daily_rate' => '15.00',
        ],
        'SILVER' => [
            'name' => 'Silver Protection',
            'base_daily_rate' => '15.00',
        ],
        'GOLD' => [
            'name' => 'Gold Protection',
            'base_daily_rate' => '25.00',
        ],
        'PLATINUM' => [
            'name' => 'Platinum Protection',
            'base_daily_rate' => '40.00',
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
        'INTERNATIONAL' => [
            'name' => 'International Travel',
            'multiplier' => '1.80',
        ],
        // Retained for legacy test compatibility
        'ASIA' => [
            'name' => 'Asia Pacific',
            'multiplier' => '1.20',
        ],
        'EUROPE' => [
            'name' => 'Europe / Schengen Area',
            'multiplier' => '1.50',
        ],
        'SCHENGEN' => [
            'name' => 'Schengen Area',
            'multiplier' => '1.25',
        ],
        'USA_CANADA' => [
            'name' => 'USA & Canada',
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
        '100000' => [
            'name' => '₹100,000 Coverage',
            'multiplier' => '1.00',
        ],
        '300000' => [
            'name' => '₹300,000 Coverage',
            'multiplier' => '1.25',
        ],
        '500000' => [
            'name' => '₹500,000 Coverage',
            'multiplier' => '1.50',
        ],
        // Retained for legacy test compatibility
        '50000' => [
            'name' => '$50,000 Coverage',
            'multiplier' => '1.00',
        ],
        '250000' => [
            'name' => '$250,000 Coverage',
            'multiplier' => '1.30',
        ],
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
            'label' => '0-17',
            'multiplier' => '1.10',
        ],
        '18_60' => [
            'min_age' => 18,
            'max_age' => 60,
            'label' => '18-60',
            'multiplier' => '1.00',
        ],
        '61_70' => [
            'min_age' => 61,
            'max_age' => 70,
            'label' => '61-70',
            'multiplier' => '1.50',
        ],
        '71_plus' => [
            'min_age' => 71,
            'max_age' => 120,
            'label' => '71+',
            'multiplier' => '2.00',
        ],
    ],
];
