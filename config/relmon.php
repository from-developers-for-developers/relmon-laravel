<?php

use FromDevelopersForDevelopers\RelMon\Enum\Format;

$customParsers = env('RELMON_CUSTOM_PARSERS', '');

return [
    'default_format' => env('RELMON_DEFAULT_FORMAT', Format::AUTO),

    'protocol_identifier' => env('RELMON_PROTOCOL_IDENTIFIER', 'relmon@1.0.0/3'),

    'defaults' => [
        'unit' => env('RELMON_DEFAULT_UNIT'),
        'scope' => env('RELMON_DEFAULT_SCOPE'),
        'roundingMode' => env('RELMON_DEFAULT_ROUNDING_MODE'),
        'roundingApplication' => env('RELMON_DEFAULT_ROUNDING_APPLICATION'),
        'taxRate' => env('RELMON_DEFAULT_TAX_RATE'),
    ],

    'custom_parsers' => array_filter(array_map('trim', explode(',', $customParsers))) ?: [
        // App\RelMon\CsvRelMonParser::class,
    ],
];
