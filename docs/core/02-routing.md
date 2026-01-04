# Routing (Yönlendirme)

## İçindekiler
1. [Temel Routing](#temel-routing)
2. [HTTP Metodları](#http-metodları)
3. [Route Parametreleri](#route-parametreleri)
4. [Middleware](#middleware)
5. [Route Groups](#route-groups)
6. [Named Routes](#named-routes)
7. [Resource Routing](#resource-routing)
8. [Gerçek Örnekler](#gerçek-örnekler)

---

## Temel Routing

### Basit Route Tanımlama

```php
use Conduit\Routing\Router;

$router = app(Router::class);

// GET request
$router->get('/users', function() {
    return new JsonResponse(['users' => User::all()]);
});

// POST request
$router->post('/users', function(Request $request) {
    $user = User::create($request->all());
    return new JsonResponse($user, 201);
});
```

### Controller Kullanımı

```php
// Controller sınıfı
class UserController {
    public function index(): JsonResponse {
        return new JsonResponse(User::all());
    }

    public function store(Request $request): JsonResponse {
        $user = User::create($request->all());
        return new JsonResponse($user, 201);
    }
}

// Route tanımı
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);

// Veya string syntax
$router->get('/users', 'UserController@index');
$router->post('/users', 'UserController@store');
```

---

## HTTP Metodları

```php
// GET - Veri al
$router->get('/posts', 'PostController@index');

// POST - Yeni kayıt oluştur
$router->post('/posts', 'PostController@store');

// PUT - Tam güncelleme
$router->put('/posts/{id}', 'PostController@update');

// PATCH - Kısmi güncelleme
$router->patch('/posts/{id}', 'PostController@update');

// DELETE - Sil
$router->delete('/posts/{id}', 'PostController@destroy');

// HEAD - Sadece header'lar
$router->head('/posts', 'PostController@head');

// OPTIONS - İzin verilen metodlar
$router->options('/posts', 'PostController@options');

// Çoklu metod
$router->match(['GET', 'POST'], '/contact', 'ContactController@handle');

// Tüm metodlar
$router->any('/webhook', 'WebhookController@handle');
```

---

## Route Parametreleri

### Basit Parametre

```php
// URL: /users/123
$router->get('/users/{id}', function($id) {
    return new JsonResponse(User::find($id));
});

// Controller ile
$router->get('/users/{id}', 'UserController@show');

class UserController {
    public function show(int $id): JsonResponse {
        $user = User::find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        return new JsonResponse($user);
    }
}
```

### Çoklu Parametre

```php
// URL: /posts/5/comments/23
$router->get('/posts/{postId}/comments/{commentId}', function($postId, $commentId) {
    $post = Post::find($postId);
    $comment = Comment::find($commentId);

    return new JsonResponse([
        'post' => $post,
        'comment' => $comment,
    ]);
});
```

### Opsiyonel Parametre

```php
// URL: /search veya /search/keywords
$router->get('/search/{query?}', function($query = null) {
    if ($query) {
        return new JsonResponse(Post::search($query));
    }

    return new JsonResponse(Post::all());
});
```

### Parametre Kısıtlamaları (Constraints)

```php
// Sadece sayı kabul et
$router->get('/users/{id}', 'UserController@show')
    ->where('id', '[0-9]+');

// Sadece harf kabul et
$router->get('/category/{slug}', 'CategoryController@show')
    ->where('slug', '[a-z-]+');

// Çoklu constraint
$router->get('/posts/{year}/{month}', 'PostController@archive')
    ->where([
        'year' => '[0-9]{4}',
        'month' => '[0-9]{2}',
    ]);

// Global constraint (tüm route'larda geçerli)
$router->pattern('id', '[0-9]+');
$router->pattern('slug', '[a-z0-9-]+');
```

---

## Middleware

### Route'a Middleware Ekleme

```php
// Tek middleware
$router->get('/admin/dashboard', 'AdminController@dashboard')
    ->middleware('auth');

// Çoklu middleware
$router->post('/posts', 'PostController@store')
    ->middleware(['auth', 'permission:posts.create']);

// Middleware parametresi ile
$router->get('/api/users', 'UserController@index')
    ->middleware('throttle:60,1'); // 60 request per minute
```

### Gerçek Örnekler

```php
// Admin route'ları - authentication gerekli
$router->get('/admin/users', 'Admin\UserController@index')
    ->middleware('auth');

// API rate limiting
$router->post('/api/login', 'AuthController@login')
    ->middleware('throttle:5,1'); // 5 attempt per minute

// Permission kontrolü
$router->delete('/users/{id}', 'UserController@destroy')
    ->middleware('permission:users.delete');

// Çoklu middleware chain
$router->put('/posts/{id}', 'PostController@update')
    ->middleware(['auth', 'permission:posts.update', 'throttle:30,1']);
```

---

## Route Groups

### Prefix ile Gruplama

```php
// API v1 grubu
$router->group(['prefix' => 'api/v1'], function($router) {
    $router->get('/users', 'Api\V1\UserController@index');
    $router->post('/users', 'Api\V1\UserController@store');
    $router->get('/posts', 'Api\V1\PostController@index');
});

// Sonuç:
// GET /api/v1/users
// POST /api/v1/users
// GET /api/v1/posts
```

### Middleware ile Gruplama

```php
// Tüm admin route'larına auth middleware
$router->group(['middleware' => 'auth'], function($router) {
    $router->get('/admin/dashboard', 'AdminController@dashboard');
    $router->get('/admin/users', 'AdminController@users');
    $router->post('/admin/settings', 'AdminController@saveSettings');
});
```

### Prefix + Middleware

```php
// API route'ları: auth + throttle
$router->group([
    'prefix' => 'api',
    'middleware' => ['auth', 'throttle:60,1'],
], function($router) {
    $router->get('/profile', 'ProfileController@show');
    $router->put('/profile', 'ProfileController@update');
    $router->get('/notifications', 'NotificationController@index');
});

// Sonuç:
// GET /api/profile (auth + throttle middleware)
// PUT /api/profile (auth + throttle middleware)
// GET /api/notifications (auth + throttle middleware)
```

### Nested Groups

```php
// Admin panel
$router->group(['prefix' => 'admin', 'middleware' => 'auth'], function($router) {

    // Admin users section
    $router->group(['prefix' => 'users'], function($router) {
        $router->get('/', 'Admin\UserController@index');           // /admin/users
        $router->get('/{id}', 'Admin\UserController@show');        // /admin/users/123
        $router->delete('/{id}', 'Admin\UserController@destroy')
            ->middleware('permission:users.delete');                // Extra middleware
    });

    // Admin posts section
    $router->group(['prefix' => 'posts'], function($router) {
        $router->get('/', 'Admin\PostController@index');
        $router->post('/', 'Admin\PostController@store');
    });
});
```

---

## Named Routes

### İsim Verme

```php
// Route'a isim ver
$router->get('/profile', 'ProfileController@show')
    ->name('profile.show');

$router->post('/profile', 'ProfileController@update')
    ->name('profile.update');
```

### İsimli Route Kullanımı

```php
// URL oluştur
$url = $router->route('profile.show');
// Sonuç: /profile

// Parametreli route
$router->get('/users/{id}', 'UserController@show')
    ->name('users.show');

$url = $router->route('users.show', ['id' => 123]);
// Sonuç: /users/123

// Query string ekle
$url = $router->route('users.show', ['id' => 123, 'tab' => 'posts']);
// Sonuç: /users/123?tab=posts
```

### Redirect

```php
// Controller'da redirect
class LoginController {
    public function login(Request $request): Response {
        // ... authentication logic

        // Named route'a redirect
        return redirect()->route('dashboard');
    }
}
```

---

## Resource Routing

RESTful resource için otomatik route'lar:

```php
// Resource route
$router->resource('posts', 'PostController');

// Şu route'ları otomatik oluşturur:
// GET    /posts              -> index()   Liste
// GET    /posts/create       -> create()  Form
// POST   /posts              -> store()   Kaydet
// GET    /posts/{id}         -> show()    Göster
// GET    /posts/{id}/edit    -> edit()    Düzenle formu
// PUT    /posts/{id}         -> update()  Güncelle
// DELETE /posts/{id}         -> destroy() Sil
```

### Kısmi Resource

```php
// Sadece belirli action'lar
$router->resource('posts', 'PostController')
    ->only(['index', 'show', 'store']);

// Belirli action'ları hariç tut
$router->resource('posts', 'PostController')
    ->except(['create', 'edit']); // API'de form route'ları gereksiz
```

### API Resource

```php
// API için (create ve edit form'ları yok)
$router->apiResource('posts', 'PostController');

// Şu route'ları oluşturur:
// GET    /posts
// POST   /posts
// GET    /posts/{id}
// PUT    /posts/{id}
// DELETE /posts/{id}
```

---

## Gerçek Örnekler

### Örnek 1: Blog API

```php
// Public routes
$router->get('/', 'HomeController@index')->name('home');
$router->get('/posts', 'PostController@index')->name('posts.index');
$router->get('/posts/{slug}', 'PostController@show')->name('posts.show');

// API routes
$router->group(['prefix' => 'api/v1', 'middleware' => 'throttle:60,1'], function($router) {

    // Authentication
    $router->post('/login', 'AuthController@login');
    $router->post('/register', 'AuthController@register');

    // Protected routes
    $router->group(['middleware' => 'auth'], function($router) {
        $router->get('/profile', 'ProfileController@show');
        $router->put('/profile', 'ProfileController@update');

        // Posts (sadece kendi yazıları)
        $router->get('/my-posts', 'MyPostController@index');
        $router->post('/posts', 'PostController@store')
            ->middleware('permission:posts.create');
        $router->put('/posts/{id}', 'PostController@update')
            ->middleware('permission:posts.update');
    });
});
```

### Örnek 2: E-Commerce API

```php
// Public
$router->get('/products', 'ProductController@index');
$router->get('/products/{id}', 'ProductController@show');
$router->get('/categories', 'CategoryController@index');

// Shopping cart
$router->group(['prefix' => 'cart'], function($router) {
    $router->get('/', 'CartController@index');
    $router->post('/items', 'CartController@addItem');
    $router->delete('/items/{id}', 'CartController@removeItem');
});

// Checkout (auth required)
$router->group(['middleware' => 'auth'], function($router) {
    $router->post('/checkout', 'CheckoutController@process');
    $router->get('/orders', 'OrderController@index');
    $router->get('/orders/{id}', 'OrderController@show');
});

// Admin (auth + permission)
$router->group([
    'prefix' => 'admin',
    'middleware' => ['auth', 'permission:admin.access']
], function($router) {
    $router->resource('products', 'Admin\ProductController');
    $router->resource('orders', 'Admin\OrderController')->only(['index', 'show', 'update']);
    $router->resource('users', 'Admin\UserController');
});
```

### Örnek 3: SaaS Multi-Tenant

```php
// Public
$router->get('/', 'HomeController@index');
$router->post('/register', 'RegisterController@store');
$router->post('/login', 'LoginController@store');

// Tenant routes (subdomain: {tenant}.app.com)
$router->group(['middleware' => 'tenant'], function($router) {

    // Dashboard
    $router->get('/dashboard', 'DashboardController@index')
        ->middleware('auth')
        ->name('dashboard');

    // Team routes
    $router->group(['middleware' => 'auth'], function($router) {

        // Projects
        $router->resource('projects', 'ProjectController');

        // Team members
        $router->get('/team', 'TeamController@index')
            ->middleware('permission:team.view');
        $router->post('/team/invite', 'TeamController@invite')
            ->middleware('permission:team.invite');

        // Settings (sadece admin)
        $router->group(['middleware' => 'role:admin'], function($router) {
            $router->get('/settings', 'SettingsController@index');
            $router->put('/settings', 'SettingsController@update');
            $router->delete('/account', 'SettingsController@deleteAccount');
        });
    });
});
```

---

## Route Listesi Görüntüleme

```bash
# Terminal'de route listesi
php conduit route:list

# Çıktı:
# +--------+------------------+-------------------+---------------+
# | Method | URI              | Name              | Middleware    |
# +--------+------------------+-------------------+---------------+
# | GET    | /users           | users.index       | auth          |
# | POST   | /users           | users.store       | auth          |
# | GET    | /users/{id}      | users.show        |               |
# | PUT    | /users/{id}      | users.update      | auth          |
# | DELETE | /users/{id}      | users.destroy     | auth,admin    |
# +--------+------------------+-------------------+---------------+
```

---

## Best Practices

### ✅ YAP

```php
// 1. RESTful routing kullan
$router->get('/posts', 'PostController@index');      // Liste
$router->post('/posts', 'PostController@store');     // Oluştur
$router->get('/posts/{id}', 'PostController@show');  // Göster
$router->put('/posts/{id}', 'PostController@update'); // Güncelle
$router->delete('/posts/{id}', 'PostController@destroy'); // Sil

// 2. Route isimlerini kullan
$router->get('/profile', 'ProfileController@show')->name('profile');
$url = $router->route('profile'); // URL değişirse otomatik güncellenir

// 3. Group kullan - tekrar yazmaktan kaçın
$router->group(['prefix' => 'api', 'middleware' => 'auth'], function($router) {
    // Tüm route'lar /api prefix'i ve auth middleware'i alır
});

// 4. Constraint kullan - validation
$router->get('/users/{id}', 'UserController@show')
    ->where('id', '[0-9]+'); // Sadece sayı
```

### ❌ YAPMA

```php
// 1. Uzun, karmaşık URL'ler
$router->get('/admin/panel/users/management/list', ...); // ❌ Çok uzun

// 2. Middleware'leri her route'da tekrarla
$router->get('/admin/users', ...)->middleware('auth');
$router->get('/admin/posts', ...)->middleware('auth'); // ❌ Tekrar
// Group kullan!

// 3. URL'leri hard-code et
$redirectUrl = '/profile'; // ❌
$redirectUrl = $router->route('profile'); // ✅
```

---

## Özet

- ✅ `get()`, `post()`, `put()`, `delete()` - HTTP metodları
- ✅ `{parametre}` - Route parametreleri
- ✅ `->where()` - Parametre kısıtlamaları
- ✅ `->middleware()` - Middleware ekleme
- ✅ `->name()` - Route isimlendirme
- ✅ `group()` - Route gruplama
- ✅ `resource()` - RESTful routing

**Altın Kural:** RESTful convention'ları takip et, grupla, isimlendire ve middleware kullan!
