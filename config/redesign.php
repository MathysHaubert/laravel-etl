<?php

return [
    'tables' => [
        //        'old_table_name' => 'new_table_name'
    ],

    'processors' => [
        //        'new_table_name' => ['class': Processor::class, 'args':[]],
    ],

    'seeders' => [
        //        'new_table_name' =>  ['class': Seeder::class, 'args':[]],
    ],
    'verifiers' => [
        //        'new_table_name' => ['class': Verifier::class, 'args' => []],
    ],

    'connections' => [
        'old' => [
            'driver' => env('OLD_DB_CONNECTION', 'mysql'),
            'host' => env('OLD_DB_HOST', '127.0.0.1'),
            'port' => env('OLD_DB_PORT', '3306'),
            'database' => env('OLD_DB_DATABASE', ''),
            'username' => env('OLD_DB_USERNAME', ''),
            'password' => env('OLD_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
    ],
];
