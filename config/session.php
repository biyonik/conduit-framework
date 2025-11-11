<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Varsayılan Session Driver
    |--------------------------------------------------------------------------
    |
    | Session'ların nerede saklanacağını belirler.
    | API-first framework için genelde JWT kullanılır (stateless).
    |
    | Desteklenen: "file", "cookie", "database", "array"
    |
    */
    'driver' => env('SESSION_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Session süresi (dakika cinsinden).
    |
    */
    'lifetime' => (int) env('SESSION_LIFETIME', 120), // 2 saat

    /*
    |--------------------------------------------------------------------------
    | Session Expiration On Close
    |--------------------------------------------------------------------------
    |
    | Browser kapatıldığında session sonlansın mı?
    |
    */
    'expire_on_close' => false,

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    |
    | Session data şifrelensin mi?
    | Hassas veri varsa true yapılmalı.
    |
    */
    'encrypt' => false,

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    |
    | File driver kullanılıyorsa session dosyaları nerede saklanır.
    |
    */
    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Database Session Connection
    |--------------------------------------------------------------------------
    |
    | Database driver kullanılıyorsa hangi connection.
    |
    */
    'connection' => null, // null = default

    /*
    |--------------------------------------------------------------------------
    | Database Session Table
    |--------------------------------------------------------------------------
    |
    | Session'ların saklandığı tablo adı.
    |
    */
    'table' => 'sessions',

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Session cookie'sinin adı.
    |
    */
    'cookie' => env('SESSION_COOKIE', 'conduit_session'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    |
    | Cookie'nin geçerli olduğu path.
    |
    */
    'path' => '/',

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    |
    | Cookie'nin geçerli olduğu domain.
    |
    */
    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    |
    | Cookie sadece HTTPS'te mi gönderilsin?
    | Production'da MUTLAKA true olmalı!
    |
    */
    'secure' => env('SESSION_SECURE_COOKIE', false),

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    |
    | JavaScript'ten cookie erişimi engellensin mi? (XSS koruması)
    | MUTLAKA true olmalı!
    |
    */
    'http_only' => true,

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | CSRF koruması için SameSite policy.
    | Değerler: "lax", "strict", "none", null
    |
    */
    'same_site' => 'lax',

    /*
    |--------------------------------------------------------------------------
    | Session Lottery
    |--------------------------------------------------------------------------
    |
    | Garbage collection için probability/divisor.
    | [2, 100] = %2 ihtimalle GC çalışır.
    |
    */
    'lottery' => [2, 100],
];