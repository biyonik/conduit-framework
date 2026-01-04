# Cache Sistemi

## İçindekiler
1. [Giriş](#giriş)
2. [Konfigürasyon](#konfigürasyon)
3. [Temel Kullanım](#temel-kullanım)
4. [Cache Driverlari](#cache-driverlari)
5. [İleri Seviye](#ileri-seviye)
6. [Gerçek Örnekler](#gerçek-örnekler)

---

## Giriş

Cache sistemi, sık kullanılan verileri hızlı erişim için bellekte veya dosya sisteminde saklar.

### Avantajlar
- ⚡ **Performans**: Database sorgularını azaltır
- 💰 **Maliyet**: Sunucu yükünü düşürür
- 🚀 **Hız**: Milisaniyeler yerine mikrosaniyeler

---

## Konfigürasyon

### config/cache.php

```php
return [
    // Default driver
    'default' => env('CACHE_DRIVER', 'file'),

    // Cache key prefix
    'prefix' => env('CACHE_PREFIX', 'conduit_cache'),

    // Stores
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('cache/data'),
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'cache',
        ],

        'array' => [
            'driver' => 'array',
        ],
    ],
];
```

### .env Ayarları

```env
CACHE_DRIVER=file
CACHE_PREFIX=myapp_
```

---

## Temel Kullanım

### Cache'e Yazma

```php
// Helper function
cache()->set('key', 'value', 3600); // 3600 saniye = 1 saat

// Veya CacheManager
use Conduit\Cache\CacheManager;

$cache = app(CacheManager::class);
$cache->set('user_count', 1500, 3600);
```

### Cache'den Okuma

```php
// Değer var mı kontrol et
$value = cache()->get('key');

if ($value === null) {
    // Cache'de yok
}

// Default değer ile
$value = cache()->get('key', 'default_value');

// Veya has() ile kontrol
if (cache()->has('key')) {
    $value = cache()->get('key');
}
```

### Cache'den Silme

```php
// Tek key sil
cache()->delete('key');

// Tüm cache'i temizle
cache()->clear();
```

---

## Cache Driverlari

### 1. File Driver (Shared Hosting İçin İdeal)

```php
// config/cache.php
'stores' => [
    'file' => [
        'driver' => 'file',
        'path' => storage_path('cache/data'),
        'permissions' => [
            'file' => 0644,
            'dir' => 0755,
        ],
    ],
],

// Kullanım
cache()->set('products', $products, 3600);
```

**Özellikler:**
- ✅ Her hosting'te çalışır
- ✅ Kurulum gerektirmez
- ✅ Atomic write (race condition safe)
- ✅ Subdirectory sharding (performance)
- ✅ Otomatik garbage collection

### 2. Database Driver

```php
// Migration önce çalıştır
php conduit migrate

// config/cache.php
'stores' => [
    'database' => [
        'driver' => 'database',
        'table' => 'cache',
    ],
],

// Kullanım
cache()->driver('database')->set('key', 'value', 3600);
```

**Ne Zaman Kullan:**
- File sistem yavaş
- Birden fazla sunucu var (shared database)
- Database zaten var ve hızlı

### 3. Array Driver (Testing)

```php
// Sadece request süresince bellekte
cache()->driver('array')->set('key', 'value');
```

**Ne Zaman Kullan:**
- Unit testing
- Development
- Geçici data

---

## İleri Seviye

### remember() - Cache veya Hesapla

```php
// Eğer cache'de varsa al, yoksa hesapla ve cache'le
$users = cache()->remember('all_users', 3600, function() {
    return User::all();
});

// İlk çağrı: Database'den çeker ve cache'ler
// Sonraki çağrılar: Cache'den alır (çok hızlı!)
```

### rememberForever() - Süresiz Cache

```php
// Hiç expire olmaz (manuel sil)
$settings = cache()->rememberForever('app_settings', function() {
    return Setting::all();
});

// Ayarlar değiştiğinde manuel sil
cache()->delete('app_settings');
```

### pull() - Al ve Sil

```php
// Cache'den al ve sil (bir kere kullanımlık)
$token = cache()->pull('reset_token_' . $userId);

if ($token) {
    // Token kullan (artık cache'de yok)
}
```

### add() - Yoksa Ekle

```php
// Sadece yoksa ekle (varsa false döner)
$added = cache()->add('lock_key', true, 60);

if ($added) {
    // Lock alındı, işlem yap
    processJob();
    cache()->delete('lock_key');
} else {
    // Lock başkası tarafından alınmış
}
```

### increment() & decrement()

```php
// Sayaç arttır
cache()->increment('page_views'); // 1 arttır
cache()->increment('login_attempts', 5); // 5 arttır

// Sayaç azalt
cache()->decrement('stock_count');
cache()->decrement('credits', 10);

// Kullanım örneği: Rate limiting
$attempts = cache()->get('login_attempts_' . $ip, 0);
cache()->increment('login_attempts_' . $ip);

if ($attempts > 5) {
    return new JsonResponse(['error' => 'Too many attempts'], 429);
}
```

### Batch Operations

```php
// Çoklu get
$values = cache()->getMultiple(['key1', 'key2', 'key3']);
// ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3']

// Çoklu set
cache()->setMultiple([
    'key1' => 'value1',
    'key2' => 'value2',
], 3600);

// Çoklu delete
cache()->deleteMultiple(['key1', 'key2', 'key3']);
```

---

## Gerçek Örnekler

### Örnek 1: User Listesi Cache

```php
class UserController {
    public function index(): JsonResponse {
        // Cache key
        $cacheKey = 'users_list_page_' . request()->input('page', 1);

        // Remember ile cache
        $users = cache()->remember($cacheKey, 600, function() {
            return User::paginate(20);
        });

        return new JsonResponse($users);
    }

    public function store(Request $request): JsonResponse {
        $user = User::create($request->all());

        // Cache'i invalidate et (yeni user eklendi)
        cache()->clear(); // Veya pattern ile sil

        return new JsonResponse($user, 201);
    }
}
```

### Örnek 2: Product Catalog (Hierarchy)

```php
class ProductService {
    public function getCategories(): array {
        return cache()->rememberForever('product_categories', function() {
            return Category::with('products')->get()->toArray();
        });
    }

    public function getProduct(int $id): ?array {
        $cacheKey = "product_{$id}";

        return cache()->remember($cacheKey, 3600, function() use ($id) {
            $product = Product::with(['category', 'images'])->find($id);
            return $product ? $product->toArray() : null;
        });
    }

    public function updateProduct(int $id, array $data): void {
        Product::where('id', $id)->update($data);

        // Cache'i invalidate et
        cache()->delete("product_{$id}");

        // Category cache de invalidate (product değişti)
        cache()->delete('product_categories');
    }
}
```

### Örnek 3: API Response Cache

```php
class ApiController {
    public function stats(): JsonResponse {
        $cacheKey = 'api_stats_' . date('Y-m-d-H'); // Saatlik cache

        $stats = cache()->remember($cacheKey, 3600, function() {
            return [
                'total_users' => User::count(),
                'active_users' => User::where('last_login_at', '>', time() - 86400)->count(),
                'total_posts' => Post::count(),
                'total_comments' => Comment::count(),
            ];
        });

        return new JsonResponse($stats);
    }
}
```

### Örnek 4: Session-like Usage

```php
// Shopping cart cache (session yerine)
class CartService {
    private function getCartKey(string $sessionId): string {
        return "cart_{$sessionId}";
    }

    public function addItem(string $sessionId, int $productId, int $quantity): void {
        $cart = cache()->get($this->getCartKey($sessionId), []);

        $cart[$productId] = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'added_at' => time(),
        ];

        // 7 gün cache
        cache()->set($this->getCartKey($sessionId), $cart, 7 * 86400);
    }

    public function getCart(string $sessionId): array {
        return cache()->get($this->getCartKey($sessionId), []);
    }

    public function clearCart(string $sessionId): void {
        cache()->delete($this->getCartKey($sessionId));
    }
}
```

### Örnek 5: Query Result Cache

```php
class ReportService {
    public function getSalesReport(string $startDate, string $endDate): array {
        $cacheKey = "sales_report_{$startDate}_{$endDate}";

        return cache()->remember($cacheKey, 1800, function() use ($startDate, $endDate) {
            // Ağır SQL query
            return DB::select("
                SELECT
                    DATE(created_at) as date,
                    SUM(total) as total_sales,
                    COUNT(*) as order_count
                FROM orders
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at)
            ", [$startDate, $endDate]);
        });
    }
}
```

### Örnek 6: Rate Limiting with Cache

```php
class RateLimiter {
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool {
        $cacheKey = "rate_limit_{$key}";

        $attempts = cache()->get($cacheKey, 0);

        if ($attempts >= $maxAttempts) {
            return false; // Rate limit exceeded
        }

        cache()->set($cacheKey, $attempts + 1, $decaySeconds);

        return true;
    }
}

// Kullanım
$limiter = new RateLimiter();

if (!$limiter->attempt($ip, 5, 60)) {
    return new JsonResponse(['error' => 'Too many requests'], 429);
}
```

---

## Cache Invalidation Stratejileri

### 1. Time-Based (Zamana Dayalı)

```php
// 1 saat sonra otomatik expire olur
cache()->set('key', 'value', 3600);
```

### 2. Event-Based (Olay Tabanlı)

```php
// User güncellendi -> cache sil
class UserController {
    public function update(int $id, Request $request): JsonResponse {
        User::where('id', $id)->update($request->all());

        // Cache invalidate
        cache()->delete("user_{$id}");
        cache()->delete("user_list");

        return new JsonResponse(['success' => true]);
    }
}
```

### 3. Tag-Based (Etiket Tabanlı) - Advanced

```php
// İleride eklenebilir (Redis ile)
cache()->tags(['users', 'premium'])->set('key', 'value');
cache()->tags(['users'])->flush(); // Tüm user cache'ini sil
```

---

## Best Practices

### ✅ YAP

```php
// 1. remember() kullan - temiz kod
$users = cache()->remember('users', 3600, fn() => User::all());

// 2. Descriptive key'ler kullan
$key = "user_profile_{$userId}_lang_{$lang}";

// 3. TTL belirle - süresiz cache tehlikeli
cache()->set('key', 'value', 3600); // 1 hour

// 4. Invalidation planla
public function updateUser($id, $data) {
    User::update($id, $data);
    cache()->delete("user_{$id}"); // Invalidate
}
```

### ❌ YAPMA

```php
// 1. Çok uzun TTL
cache()->set('key', 'value', 86400 * 365); // ❌ 1 yıl çok uzun

// 2. Sensitive data cache'leme
cache()->set('password', $password); // ❌ ASLA!
cache()->set('credit_card', $cc); // ❌ ASLA!

// 3. Cache'e körü körüne güvenme
$user = cache()->get('user_' . $id);
// $user null olabilir, kontrol et!

// 4. Çok büyük data cache'leme
cache()->set('all_logs', $millionsOfLogs); // ❌ Memory problemi
```

---

## Garbage Collection

### Manuel GC

```php
// File driver için
$fileDriver = cache()->driver('file');
$deleted = $fileDriver->gc(); // Expired cache'leri sil
echo "Deleted {$deleted} expired cache entries";

// Database driver için
$dbDriver = cache()->driver('database');
$deleted = $dbDriver->gc();
```

### Otomatik GC (Cron)

```bash
# crontab -e
# Her gün gece 2'de expired cache'leri temizle
0 2 * * * cd /path/to/app && php conduit cache:gc
```

---

## Performance Tips

### 1. Sık Kullanılan Data Cache'le

```php
// ❌ Kötü - Her istekte database
public function getSettings() {
    return Setting::all();
}

// ✅ İyi - Cache'le
public function getSettings() {
    return cache()->rememberForever('settings', fn() => Setting::all());
}
```

### 2. Cache Warming

```php
// Uygulama açılışında cache doldur
class CacheWarmupCommand {
    public function handle() {
        cache()->set('popular_products', Product::popular()->get());
        cache()->set('categories', Category::all());
        cache()->set('settings', Setting::all());
    }
}
```

### 3. Partial Caching

```php
// ❌ Tüm sayfa cache'leme - flexible değil
cache()->set('home_page', $entirePage);

// ✅ Component bazlı cache
cache()->set('home_featured_posts', $posts);
cache()->set('home_categories', $categories);
cache()->set('home_ads', $ads);
```

---

## Özet

- ✅ `set()` - Cache'e yaz
- ✅ `get()` - Cache'den oku
- ✅ `remember()` - Varsa al, yoksa hesapla
- ✅ `delete()` - Sil
- ✅ `clear()` - Hepsini sil
- ✅ File driver - Shared hosting için
- ✅ Database driver - Alternative
- ✅ Array driver - Testing için

**Altın Kural:** Sık kullanılan, az değişen data'yı cache'le!
