<?php

declare(strict_types=1);

use Vellum\Config\PaperSize;

return [

    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    |
    | The driver used when a document does not explicitly select one. Shipped
    | drivers: "dompdf", "browsershot", "mpdf" and "fake" (testing only).
    |
    */

    'default_driver' => env('PDF_DRIVER', 'dompdf'),

    /*
    |--------------------------------------------------------------------------
    | Global defaults
    |--------------------------------------------------------------------------
    |
    | These values apply to every document unless a driver specific default or
    | a per document override takes precedence. Layering order is:
    |
    |     global defaults  <  driver defaults  <  document overrides
    |
    */

    'defaults' => [
        'paper' => PaperSize::A4->value,
        'orientation' => 'portrait',
        'font' => null,
        'font_file' => null,
        'dpi' => 96,
        'margins' => [
            'top' => 10.0,
            'right' => 10.0,
            'bottom' => 10.0,
            'left' => 10.0,
        ],
        'temp_folder' => null,
        'font_cache_path' => null,
        'allow_remote_assets' => false,
        'timeout' => 30,
        'fail_on_warnings' => false,
        'options' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Per driver defaults
    |--------------------------------------------------------------------------
    |
    | Anything supported by the global defaults can be overridden per driver,
    | plus engine specific "options" passed straight to the underlying engine.
    |
    | The options set on a document always win over these ones.
    |
    */

    'drivers' => [

        'dompdf' => [
            'options' => [
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
            ],
        ],

        'browsershot' => [
            'options' => [
                'printBackground' => true,
                'scale' => 1,
                'preferCSSPageSize' => false,
                'node_modules_path' => env('BROWSERSHOT_NODE_MODULES_PATH', base_path('node_modules')),
            ],
        ],

        'mpdf' => [
            'options' => [],
        ],

        'fake' => [
            'options' => [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | Settings for the built-in source types.
    |
    */

    'sources' => [

        'twig' => [
            'paths' => [
                function_exists('resource_path') ? resource_path('views') : base_path('resources/views'),
            ],
            'extension' => 'twig',
        ],

        'url' => [
            'allow_remote' => true,
            'timeout' => 15,
            'headers' => [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Post processors
    |--------------------------------------------------------------------------
    |
    | The list of post processors that run for every document. Each entry is
    | the name of a class implementing Vellum\Contracts\Processor, it is
    | resolved from the container.
    |
    */

    'processors' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Every render logs the driver, the source type, the render time and the
    | output size. Set the channel to null to use the application default.
    |
    */

    'logging' => [
        'channel' => null,
        'level' => 'info',
    ],

];
