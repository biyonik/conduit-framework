<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Uygulama Adı
    |--------------------------------------------------------------------------
    |
    | Uygulamanızın adı. Log kayıtlarında ve hata mesajlarında kullanılır.
    |
    */
    'name' => env('APP_NAME', 'Conduit'),

    /*
    |--------------------------------------------------------------------------
    | Uygulama Ortamı
    |--------------------------------------------------------------------------
    |
    | Bu değer uygulamanızın çalıştığı ortamı belirler.
    | Değerler: "local", "development", "staging", "production"
    |
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Debug Modu
    |--------------------------------------------------------------------------
    |
    | Debug açıkken detaylı hata mesajları gösterilir.
    | ÖNEMLİ: Production'da MUTLAKA false olmalı!
    |
    */
    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Uygulama URL'si
    |--------------------------------------------------------------------------
    |
    | Uygulamanızın çalıştığı URL. Route generation ve redirect'lerde kullanılır.
    |
    */
    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Uygulama Şifreleme Anahtarı
    |--------------------------------------------------------------------------
    |
    | AES-256-GCM şifreleme için kullanılan anahtar.
    | ÖNEMLİ: Bu değer 32 byte (256 bit) olmalı!
    |
    | Oluşturmak için: php conduit key:generate
    |
    */
    'key' => env('APP_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Şifreleme Cipher'ı
    |--------------------------------------------------------------------------
    |
    | Desteklenen: "AES-256-GCM" (önerilen), "AES-256-CBC"
    |
    */
    'cipher' => 'AES-256-GCM',

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | Uygulamanızın varsayılan zaman dilimi.
    |
    */
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    |
    | Varsayılan dil ayarı (gelecekte i18n için).
    |
    */
    'locale' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | Varsayılan dil bulunamazsa kullanılacak yedek dil.
    |
    */
    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Service Provider'lar
    |--------------------------------------------------------------------------
    |
    | Uygulama başlatılırken yüklenecek service provider'lar.
    | Sıralama önemli! Core provider'lar önce yüklenmeli.
    |
    */
    'providers' => [
        // Core Providers (Framework)
        \Conduit\Core\CoreServiceProvider::class,
        \Conduit\Http\HttpServiceProvider::class,
        \Conduit\Routing\RoutingServiceProvider::class,
        \Conduit\Database\DatabaseServiceProvider::class,
        \Conduit\Cache\CacheServiceProvider::class,
        \Conduit\Security\SecurityServiceProvider::class,
        \Conduit\Validation\ValidationServiceProvider::class,
        \Conduit\Queue\QueueServiceProvider::class,

        // Application Providers (User)
        // \App\Providers\AuthServiceProvider::class,
        // \App\Providers\RouteServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | Kısa isimlerle sınıflara erişim (Laravel style facades).
    | Opsiyonel özellik, shared hosting'te performans için kapalı tutulabilir.
    |
    */
    'aliases' => [
        'App' => \Conduit\Support\Facades\App::class,
        'Cache' => \Conduit\Support\Facades\Cache::class,
        'DB' => \Conduit\Support\Facades\DB::class,
        'Hash' => \Conduit\Support\Facades\Hash::class,
        'Validator' => \Conduit\Support\Facades\Validator::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode
    |--------------------------------------------------------------------------
    |
    | Bakım modu aktifken tüm istekler 503 Service Unavailable döner.
    | Dosya: storage/framework/down
    |
    */
    'maintenance' => [
        'enabled' => file_exists(storage_path('framework/down')),
        'message' => 'We are currently performing maintenance. Please check back soon.',
        'retry' => 3600, // Retry-After header (seconds)
    ],
];