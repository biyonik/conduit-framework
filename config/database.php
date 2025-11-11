<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Varsayılan Database Bağlantısı
    |--------------------------------------------------------------------------
    |
    | Birden fazla bağlantı tanımlanabilir, hangisinin varsayılan olacağını belirler.
    |
    */
    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Bağlantıları
    |--------------------------------------------------------------------------
    |
    | Her bağlantı için driver, host, port, database, credentials tanımlanır.
    | Desteklenen driver'lar: mysql, sqlite, pgsql
    |
    */
    'connections' => [
        /*
        |----------------------------------------------------------------------
        | MySQL / MariaDB Bağlantısı
        |----------------------------------------------------------------------
        |
        | Shared hosting'te en yaygın seçenek.
        | MySQL 5.7+ veya MariaDB 10.3+ önerilir.
        |
        */
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'conduit'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => env('DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => [
                // PDO seçenekleri
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // True prepared statements
                PDO::ATTR_STRINGIFY_FETCHES => false, // Type casting
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | SQLite Bağlantısı
        |----------------------------------------------------------------------
        |
        | Hafif projeler veya development için uygundur.
        | Production'da dikkatli kullanılmalı (concurrency sınırlı).
        |
        */
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | PostgreSQL Bağlantısı
        |----------------------------------------------------------------------
        |
        | Shared hosting'te nadirdir ama güçlü bir seçenektir.
        |
        */
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'conduit'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | Migration geçmişinin saklandığı tablo adı.
    |
    */
    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Konfigürasyonu (Opsiyonel)
    |--------------------------------------------------------------------------
    |
    | Shared hosting'te genelde mevcut değildir.
    | VPS/Dedicated sunucularda cache/queue için kullanılabilir.
    |
    */
    'redis' => [
        'client' => 'phpredis', // phpredis veya predis
        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],
        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],
    ],
];