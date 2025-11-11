<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Varsayılan Cache Store
    |--------------------------------------------------------------------------
    |
    | Kullanılacak cache driver'ı belirler.
    | Shared hosting için 'file' veya 'database' önerilir.
    |
    | Desteklenen: "file", "database", "array", "redis" (VPS'te)
    |
    */
    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Store'ları
    |--------------------------------------------------------------------------
    |
    | Her cache driver için konfigürasyon.
    |
    */
    'stores' => [
        /*
        |----------------------------------------------------------------------
        | File Cache Driver
        |----------------------------------------------------------------------
        |
        | Dosya sisteminde cache saklar.
        | Shared hosting'te en iyi performans için OPcache ile optimize edilir.
        |
        | Avantajlar:
        | ✅ Her hosting'te çalışır
        | ✅ Kurulum gerektirmez
        | ✅ OPcache boost alır
        |
        | Dezavantajlar:
        | ❌ Disk I/O overhead
        | ❌ Redis'ten yavaş
        |
        */
        'file' => [
            'driver' => 'file',
            'path' => storage_path('cache/data'),
            'permissions' => [
                'file' => 0644,
                'dir' => 0755,
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Database Cache Driver
        |----------------------------------------------------------------------
        |
        | MySQL/MariaDB'de cache saklar.
        | Shared hosting'te Redis yoksa iyi alternatiftir.
        |
        | Tablo: cache
        | Schema: key (VARCHAR 255 PK), value (LONGTEXT), expiration (INT)
        |
        */
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null, // null = default connection
        ],

        /*
        |----------------------------------------------------------------------
        | Array Cache Driver
        |----------------------------------------------------------------------
        |
        | In-memory cache (sadece request süresince).
        | Testing ve development için kullanılır.
        |
        */
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        /*
        |----------------------------------------------------------------------
        | Redis Cache Driver (Opsiyonel)
        |----------------------------------------------------------------------
        |
        | Shared hosting'te genelde YOK!
        | VPS/Dedicated sunucularda kullanılabilir.
        |
        */
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Cache key'lerinin önüne eklenen prefix.
    | Çoklu uygulama aynı cache'i kullanıyorsa collision önlenir.
    |
    */
    'prefix' => env('CACHE_PREFIX', 'conduit_cache'),

    /*
    |--------------------------------------------------------------------------
    | Cache Tagging Support
    |--------------------------------------------------------------------------
    |
    | Cache tag'leri destekleyen driver'lar: redis, array
    | File ve database driver'ları tag desteklemez.
    |
    */
    'tags' => [
        'enabled' => false, // Shared hosting'te kapalı (file cache)
    ],
];