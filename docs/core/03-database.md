# Database & ORM

## İçindekiler
1. [Giriş](#giriş)
2. [Konfigürasyon](#konfigürasyon)
3. [Query Builder](#query-builder)
4. [Models (Active Record)](#models-active-record)
5. [İlişkiler (Relationships)](#i̇lişkiler-relationships)
6. [Migrations](#migrations)
7. [Pagination](#pagination)
8. [Gerçek Örnekler](#gerçek-örnekler)

---

## Giriş

Conduit Framework modern bir database katmanı sunar:
- **Query Builder**: Fluent interface ile SQL sorguları
- **Active Record ORM**: Model tabanlı data yönetimi
- **Relationships**: hasOne, hasMany, belongsTo, belongsToMany
- **Migrations**: Version kontrolü ile schema yönetimi
- **Pagination**: Offset-based sayfalama
- **Type Safety**: Strict types ve casting

---

## Konfigürasyon

### config/database.php

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'conduit'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
        ],
    ],
];
```

### .env Dosyası

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
```

---

## Query Builder

### Temel Sorgular

```php
use Conduit\Database\DB;

// SELECT * FROM users
$users = DB::table('users')->get();

// SELECT * FROM users WHERE active = 1
$activeUsers = DB::table('users')
    ->where('active', '=', 1)
    ->get();

// SELECT name, email FROM users WHERE age > 18
$adults = DB::table('users')
    ->select(['name', 'email'])
    ->where('age', '>', 18)
    ->get();

// SELECT * FROM users WHERE status = 'active' ORDER BY created_at DESC LIMIT 10
$recentUsers = DB::table('users')
    ->where('status', '=', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();
```

### Where Clauses

```php
// Basit where
DB::table('users')->where('email', '=', 'john@example.com')->first();

// Çoklu where (AND)
DB::table('products')
    ->where('price', '>', 100)
    ->where('stock', '>', 0)
    ->get();

// WHERE IN
DB::table('users')
    ->whereIn('id', [1, 2, 3, 4, 5])
    ->get();

// WHERE LIKE
DB::table('products')
    ->where('name', 'LIKE', '%laptop%')
    ->get();

// WHERE NULL
DB::table('users')
    ->whereNull('deleted_at')
    ->get();

// WHERE BETWEEN
DB::table('orders')
    ->whereBetween('total', [100, 500])
    ->get();
```

### Joins

```php
// INNER JOIN
$posts = DB::table('posts')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->select(['posts.*', 'users.name as author_name'])
    ->get();

// LEFT JOIN
$posts = DB::table('posts')
    ->leftJoin('categories', 'posts.category_id', '=', 'categories.id')
    ->select(['posts.*', 'categories.name as category_name'])
    ->get();
```

### Agregat Fonksiyonlar

```php
// COUNT
$userCount = DB::table('users')->count();

// SUM
$totalSales = DB::table('orders')->sum('total');

// AVG
$averagePrice = DB::table('products')->avg('price');

// MAX / MIN
$highestPrice = DB::table('products')->max('price');
$lowestPrice = DB::table('products')->min('price');

// GROUP BY ile
$salesByMonth = DB::table('orders')
    ->select(['MONTH(created_at) as month', 'SUM(total) as total_sales'])
    ->groupBy('month')
    ->get();
```

### Insert / Update / Delete

```php
// INSERT
$userId = DB::table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'created_at' => time(),
]);

// Bulk INSERT
DB::table('products')->insertBulk([
    ['name' => 'Product 1', 'price' => 99.99],
    ['name' => 'Product 2', 'price' => 149.99],
    ['name' => 'Product 3', 'price' => 199.99],
]);

// UPDATE
$affected = DB::table('users')
    ->where('id', '=', 1)
    ->update(['name' => 'Jane Doe']);

// DELETE
$deleted = DB::table('users')
    ->where('status', '=', 'inactive')
    ->delete();
```

### Raw SQL (Dikkatli Kullanın!)

```php
// Parametreli raw query (güvenli)
$users = DB::select(
    "SELECT * FROM users WHERE age > ? AND country = ?",
    [18, 'TR']
);

// INSERT raw
DB::statement(
    "INSERT INTO logs (message, level, created_at) VALUES (?, ?, ?)",
    ['User logged in', 'info', time()]
);

// Production'da raw SQL kısıtlaması
// config/database.php
'allow_raw_sql' => env('DB_ALLOW_RAW_SQL', false),
```

---

## Models (Active Record)

### Model Tanımlama

```php
// app/Models/User.php
namespace App\Models;

use Conduit\Database\Model;

class User extends Model {
    // Tablo adı (opsiyonel, otomatik 'users' olur)
    protected string $table = 'users';

    // Mass-assignment için izin verilen alanlar
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];

    // Type casting
    protected array $casts = [
        'id' => 'int',
        'active' => 'bool',
        'created_at' => 'int',
    ];

    // Gizli alanlar (toArray/toJson'da gösterilmez)
    protected array $hidden = [
        'password',
    ];
}
```

### Model Kullanımı

```php
use App\Models\User;

// Tüm kayıtları çek
$users = User::all();

// ID ile bul
$user = User::find(1);

// Where ile bul
$user = User::where('email', '=', 'john@example.com')->first();

// Yeni kayıt oluştur
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT),
]);

// Güncelle
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// Veya toplu güncelle
$user->update(['name' => 'Jane Doe']);

// Sil
$user = User::find(1);
$user->delete();

// Toplu silme
User::where('active', '=', 0)->delete();
```

### Scopes (Yeniden Kullanılabilir Sorgular)

```php
// Model'de scope tanımla
class Post extends Model {
    public function scopePublished($query) {
        return $query->where('status', '=', 'published')
                     ->where('published_at', '<=', time());
    }

    public function scopeByAuthor($query, int $userId) {
        return $query->where('user_id', '=', $userId);
    }
}

// Kullanım
$publishedPosts = Post::published()->get();

$myPosts = Post::byAuthor($userId)->get();

// Birleştir
$myPublishedPosts = Post::published()
    ->byAuthor($userId)
    ->orderBy('published_at', 'DESC')
    ->get();
```

---

## İlişkiler (Relationships)

### hasOne (Bire Bir)

```php
// User has one Profile
class User extends Model {
    public function profile() {
        return $this->hasOne(Profile::class, 'user_id');
    }
}

class Profile extends Model {
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}

// Kullanım
$user = User::find(1);
$profile = $user->profile()->first();
// veya
$profile = $user->profile;
```

### hasMany (Bire Çok)

```php
// User has many Posts
class User extends Model {
    public function posts() {
        return $this->hasMany(Post::class, 'user_id');
    }
}

class Post extends Model {
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}

// Kullanım
$user = User::find(1);
$posts = $user->posts()->get();

// Filtreleme
$publishedPosts = $user->posts()
    ->where('status', '=', 'published')
    ->get();

// Count
$postCount = $user->posts()->count();
```

### belongsTo (Ters İlişki)

```php
class Post extends Model {
    public function author() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }
}

// Kullanım
$post = Post::find(1);
$author = $post->author()->first();
$category = $post->category()->first();
```

### belongsToMany (Çoka Çok)

```php
// Post has many Tags, Tag has many Posts
class Post extends Model {
    public function tags() {
        return $this->belongsToMany(
            Tag::class,        // Related model
            'post_tag',        // Pivot table
            'post_id',         // Foreign key
            'tag_id'           // Related key
        );
    }
}

class Tag extends Model {
    public function posts() {
        return $this->belongsToMany(
            Post::class,
            'post_tag',
            'tag_id',
            'post_id'
        );
    }
}

// Kullanım
$post = Post::find(1);
$tags = $post->tags()->get();

// Attach (ekle)
$post->tags()->attach(5); // tag_id = 5

// Detach (çıkar)
$post->tags()->detach(5);

// Sync (senkronize et)
$post->tags()->sync([1, 2, 3]); // Sadece bu 3 tag kalsın
```

### Eager Loading (N+1 Problemi Çözümü)

```php
// ❌ Kötü - N+1 problem
$posts = Post::all(); // 1 query
foreach ($posts as $post) {
    echo $post->author()->first()->name; // N queries
}

// ✅ İyi - Eager loading
$posts = Post::with(['author'])->get(); // 2 query
foreach ($posts as $post) {
    echo $post->author->name; // No query
}

// Çoklu ilişki
$posts = Post::with(['author', 'category', 'tags'])->get();

// İç içe ilişki
$posts = Post::with(['author.profile', 'comments.user'])->get();
```

---

## Migrations

### Migration Oluşturma

```php
// database/migrations/2026_01_05_create_users_table.php
use Conduit\Database\Schema\Migration;
use Conduit\Database\Schema\Schema;
use Conduit\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // bigint unsigned auto_increment primary key
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->index('email');
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};
```

### Tablo Tipleri

```php
Schema::create('example', function (Blueprint $table) {
    // Integer types
    $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
    $table->unsignedBigInteger('user_id');
    $table->unsignedInteger('count');
    $table->integer('balance');

    // String types
    $table->string('name', 255); // VARCHAR(255)
    $table->text('description'); // TEXT
    $table->longText('content'); // LONGTEXT

    // Decimal / Float
    $table->decimal('price', 10, 2); // DECIMAL(10,2)
    $table->float('rating');

    // Boolean
    $table->boolean('active'); // TINYINT(1)

    // JSON
    $table->json('metadata'); // JSON column

    // Timestamps
    $table->unsignedInteger('created_at');
    $table->unsignedInteger('updated_at');
    $table->unsignedInteger('deleted_at')->nullable();

    // Indexes
    $table->index('email');
    $table->unique('slug');
    $table->index(['user_id', 'status']); // Composite index

    // Foreign keys
    $table->foreign('user_id')
        ->references('id')
        ->on('users')
        ->onDelete('cascade');
});
```

### Migration Çalıştırma

```bash
# Tüm migration'ları çalıştır
php conduit migrate

# Rollback (geri al)
php conduit migrate:rollback

# Fresh (tüm tabloları sil ve yeniden oluştur)
php conduit migrate:fresh
```

---

## Pagination

### Temel Pagination

```php
// Query Builder ile
$users = DB::table('users')
    ->where('active', '=', 1)
    ->paginate(20); // Sayfa başına 20 kayıt

// Model ile
$posts = Post::published()
    ->orderBy('created_at', 'DESC')
    ->paginate(15);

// Response
return new JsonResponse([
    'data' => $posts->items(),
    'meta' => [
        'total' => $posts->total(),
        'per_page' => $posts->perPage(),
        'current_page' => $posts->currentPage(),
        'last_page' => $posts->lastPage(),
    ],
]);
```

### Manuel Pagination

```php
$page = (int) $request->query('page', 1);
$perPage = 20;

$total = Post::count();
$posts = Post::offset(($page - 1) * $perPage)
    ->limit($perPage)
    ->get();

return new JsonResponse([
    'data' => $posts,
    'meta' => [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'last_page' => ceil($total / $perPage),
    ],
]);
```

---

## Gerçek Örnekler

### Örnek 1: Blog Post Repository

```php
// app/Repositories/PostRepository.php
namespace App\Repositories;

use App\Models\Post;

class PostRepository {
    public function getPublishedPosts(int $page = 1, int $perPage = 20): array {
        $posts = Post::with(['author', 'category', 'tags'])
            ->where('status', '=', 'published')
            ->where('published_at', '<=', time())
            ->orderBy('published_at', 'DESC')
            ->paginate($perPage);

        return [
            'posts' => $posts->items(),
            'pagination' => [
                'total' => $posts->total(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
            ],
        ];
    }

    public function getPostBySlug(string $slug): ?Post {
        return Post::with(['author', 'category', 'tags', 'comments'])
            ->where('slug', '=', $slug)
            ->where('status', '=', 'published')
            ->first();
    }

    public function createPost(array $data): Post {
        return Post::create([
            'user_id' => $data['user_id'],
            'category_id' => $data['category_id'] ?? null,
            'title' => $data['title'],
            'slug' => $this->generateSlug($data['title']),
            'content' => $data['content'],
            'status' => 'draft',
        ]);
    }

    private function generateSlug(string $title): string {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);

        $originalSlug = $slug;
        $count = 1;

        while (Post::where('slug', '=', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }
}
```

### Örnek 2: E-commerce Order Service

```php
// app/Services/OrderService.php
namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

class OrderService {
    public function createOrder(int $userId, array $items, array $shippingAddress): Order {
        $subtotal = 0;
        $orderItems = [];

        // Validate and calculate
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            if (!$product || $product->stock < $item['quantity']) {
                throw new \Exception("Product {$item['product_id']} not available");
            }

            $itemSubtotal = $product->price * $item['quantity'];
            $subtotal += $itemSubtotal;

            $orderItems[] = [
                'product' => $product,
                'quantity' => $item['quantity'],
                'price' => $product->price,
                'subtotal' => $itemSubtotal,
            ];
        }

        // Calculate totals
        $tax = $subtotal * 0.18;
        $shipping = 50.00;
        $total = $subtotal + $tax + $shipping;

        // Create order
        $order = Order::create([
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $userId,
            'status' => 'pending',
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $total,
            'shipping_address' => json_encode($shippingAddress),
        ]);

        // Create order items and update stock
        foreach ($orderItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product']->id,
                'product_name' => $item['product']->name,
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['subtotal'],
            ]);

            // Decrement stock
            $item['product']->update([
                'stock' => $item['product']->stock - $item['quantity'],
            ]);
        }

        return $order;
    }

    public function getUserOrders(int $userId, int $page = 1): array {
        $orders = Order::with(['items'])
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->paginate(20);

        return [
            'orders' => $orders->items(),
            'pagination' => [
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
            ],
        ];
    }

    private function generateOrderNumber(): string {
        return 'ORD-' . time() . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }
}
```

### Örnek 3: Analytics Service

```php
// app/Services/AnalyticsService.php
namespace App\Services;

use Conduit\Database\DB;

class AnalyticsService {
    public function getDailySales(string $startDate, string $endDate): array {
        return DB::select("
            SELECT
                DATE(FROM_UNIXTIME(created_at)) as date,
                COUNT(*) as order_count,
                SUM(total) as total_sales
            FROM orders
            WHERE created_at BETWEEN ? AND ?
            AND status != 'cancelled'
            GROUP BY DATE(FROM_UNIXTIME(created_at))
            ORDER BY date ASC
        ", [strtotime($startDate), strtotime($endDate)]);
    }

    public function getTopProducts(int $limit = 10): array {
        return DB::select("
            SELECT
                p.id,
                p.name,
                SUM(oi.quantity) as total_sold,
                SUM(oi.subtotal) as total_revenue
            FROM products p
            INNER JOIN order_items oi ON p.id = oi.product_id
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE o.status != 'cancelled'
            GROUP BY p.id, p.name
            ORDER BY total_sold DESC
            LIMIT ?
        ", [$limit]);
    }

    public function getUserLifetimeValue(int $userId): float {
        $result = DB::table('orders')
            ->where('user_id', '=', $userId)
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        return (float) $result;
    }
}
```

---

## Best Practices

### ✅ YAP

```php
// 1. Model kullan (Query Builder yerine)
$users = User::where('active', '=', 1)->get();

// 2. Eager loading kullan (N+1 önle)
$posts = Post::with(['author', 'comments'])->get();

// 3. Scopes kullan (reusable queries)
$publishedPosts = Post::published()->get();

// 4. Mass assignment koruma
protected array $fillable = ['name', 'email']; // Sadece bunlar

// 5. Type casting kullan
protected array $casts = ['active' => 'bool', 'settings' => 'json'];

// 6. Transactions kullan (critical operations)
DB::beginTransaction();
try {
    $order = Order::create([...]);
    $order->items()->createMany([...]);
    DB::commit();
} catch (\Exception $e) {
    DB::rollback();
    throw $e;
}
```

### ❌ YAPMA

```php
// 1. N+1 query problemi
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author()->first()->name; // ❌ Her post için query
}

// 2. Mass assignment açığı
protected array $fillable = ['*']; // ❌ Hepsine izin verme

// 3. Raw SQL without parameters
DB::select("SELECT * FROM users WHERE id = {$_GET['id']}"); // ❌ SQL Injection!

// 4. Sensitive data exposure
// ❌ password toArray()'de gösterilmemeli
protected array $hidden = ['password'];
```

---

## Özet

- ✅ **Query Builder**: Fluent, güvenli SQL sorguları
- ✅ **Active Record ORM**: Model-based data access
- ✅ **Relationships**: hasOne, hasMany, belongsTo, belongsToMany
- ✅ **Eager Loading**: N+1 query problemini çöz
- ✅ **Migrations**: Version-controlled schema
- ✅ **Pagination**: Built-in sayfalama
- ✅ **Type Safety**: Strict types ve casting
- ✅ **Security**: SQL injection prevention

**Altın Kural:** Always use Models, always use Eager Loading, always use prepared statements!
