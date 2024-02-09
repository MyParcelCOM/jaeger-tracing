<?php

declare(strict_types=1);

return [
    'enabled'      => env('JAEGER_ENABLED', false),
    'server_name'  => env('APP_NAME', 'myparcelcom-jaeger-tracing'),
    'agent_host'   => env('JAEGER_AGENT_HOST', 'jaeger'),
    'agent_port'   => env('JAEGER_AGENT_PORT', '6831'),

    /*
    |--------------------------------------------------------------------------
    | Routes to trace (whitelist)
    |--------------------------------------------------------------------------
    |
    | This is a list of route names that are enabled for tracing. No other
    | routes will be traced.
    */
    'trace_routes' => [
//        'some-route-name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Jobs to trace (whitelist)
    |--------------------------------------------------------------------------
    |
    | This is a list of job classes that are enabled for tracing. No other
    | jobs will be traced
    */
    'trace_jobs'   => [
//        SomeJob::class,
    ],
];
