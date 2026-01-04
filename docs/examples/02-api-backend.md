# Örnek Uygulama 2: RESTful API Backend

## Proje Özeti

E-commerce API Backend:
- Ürün kataloğu yönetimi
- Sipariş sistemi
- Kullanıcı yönetimi
- JWT Authentication
- Rate limiting
- API versioning
- Pagination
- Filtreleme ve sıralama

---

## 1. Database Schema

### Migration Dosyaları

```php
// database/migrations/2026_01_05_create_api_tables.php
use Conduit\Database\Schema\Migration;
use Conduit\Database\Schema\Schema;
use Conduit\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        // Products table
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('status', 20)->default('active'); // active, inactive, out_of_stock
            $table->unsignedBigInteger('category_id')->nullable();
            $table->json('images')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->index('sku');
            $table->index('status');
            $table->index('category_id');
        });

        // Product categories
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('created_at');

            $table->index('parent_id');
        });

        // Orders table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('status', 20)->default('pending'); // pending, processing, shipped, delivered, cancelled
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('shipping', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->json('shipping_address');
            $table->json('billing_address')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('order_number');
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        // Order items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name', 255); // Snapshot
            $table->string('product_sku', 50); // Snapshot
            $table->decimal('price', 10, 2); // Snapshot
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 10, 2);

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            $table->index('order_id');
        });

        // API tokens
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token', 64)->unique();
            $table->string('name', 100)->nullable();
            $table->text('abilities')->nullable(); // JSON array of permissions
            $table->unsignedInteger('last_used_at')->nullable();
            $table->unsignedInteger('expires_at')->nullable();
            $table->unsignedInteger('created_at');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('token');
        });
    }

    public function down(): void {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
```

---

## 2. Models

### Product Model

```php
// app/Models/Product.php
namespace App\Models;

use Conduit\Database\Model;

class Product extends Model {
    protected string $table = 'products';

    protected array $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'cost',
        'stock',
        'status',
        'category_id',
        'images',
        'metadata',
    ];

    protected array $casts = [
        'id' => 'int',
        'price' => 'float',
        'cost' => 'float',
        'stock' => 'int',
        'category_id' => 'int',
        'images' => 'json',
        'metadata' => 'json',
        'created_at' => 'int',
        'updated_at' => 'int',
    ];

    // Relationships
    public function category() {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    // Scopes
    public function scopeActive($query) {
        return $query->where('status', '=', 'active');
    }

    public function scopeInStock($query) {
        return $query->where('stock', '>', 0);
    }

    // Helper methods
    public function isAvailable(): bool {
        return $this->status === 'active' && $this->stock > 0;
    }

    public function decrementStock(int $quantity): bool {
        if ($this->stock < $quantity) {
            return false;
        }

        $this->stock -= $quantity;

        if ($this->stock === 0) {
            $this->status = 'out_of_stock';
        }

        $this->save();
        return true;
    }

    public function toApiResponse(): array {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'status' => $this->status,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'images' => $this->images ?? [],
            'created_at' => $this->created_at,
        ];
    }
}
```

### Order Model

```php
// app/Models/Order.php
namespace App\Models;

use Conduit\Database\Model;

class Order extends Model {
    protected string $table = 'orders';

    protected array $fillable = [
        'order_number',
        'user_id',
        'status',
        'subtotal',
        'tax',
        'shipping',
        'total',
        'shipping_address',
        'billing_address',
        'notes',
    ];

    protected array $casts = [
        'id' => 'int',
        'user_id' => 'int',
        'subtotal' => 'float',
        'tax' => 'float',
        'shipping' => 'float',
        'total' => 'float',
        'shipping_address' => 'json',
        'billing_address' => 'json',
        'created_at' => 'int',
        'updated_at' => 'int',
    ];

    // Relationships
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items() {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    // Helper methods
    public static function generateOrderNumber(): string {
        $prefix = 'ORD';
        $timestamp = time();
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return "{$prefix}-{$timestamp}-{$random}";
    }

    public function canBeCancelled(): bool {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function toApiResponse(): array {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'shipping' => $this->shipping,
            'total' => $this->total,
            'items_count' => count($this->items()->get()),
            'shipping_address' => $this->shipping_address,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### API Token Model

```php
// app/Models/ApiToken.php
namespace App\Models;

use Conduit\Database\Model;

class ApiToken extends Model {
    protected string $table = 'api_tokens';

    protected array $fillable = [
        'user_id',
        'token',
        'name',
        'abilities',
        'expires_at',
    ];

    protected array $casts = [
        'abilities' => 'json',
        'last_used_at' => 'int',
        'expires_at' => 'int',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function generate(int $userId, string $name = 'default'): self {
        $token = bin2hex(random_bytes(32));

        return self::create([
            'user_id' => $userId,
            'token' => hash('sha256', $token),
            'name' => $name,
            'abilities' => ['*'], // All permissions by default
            'expires_at' => time() + (365 * 86400), // 1 year
        ]);
    }

    public function hasAbility(string $ability): bool {
        $abilities = $this->abilities ?? [];

        if (in_array('*', $abilities)) {
            return true;
        }

        return in_array($ability, $abilities);
    }

    public function isExpired(): bool {
        return $this->expires_at && $this->expires_at < time();
    }
}
```

---

## 3. API Controllers

### ProductController

```php
// app/Controllers/API/V1/ProductController.php
namespace App\Controllers\API\V1;

use App\Models\Product;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class ProductController {
    // GET /api/v1/products
    public function index(Request $request): JsonResponse {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $query = Product::query()->active();

        // Filtreleme
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', '=', (int) $categoryId);
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($minPrice = $request->query('min_price')) {
            $query->where('price', '>=', (float) $minPrice);
        }

        if ($maxPrice = $request->query('max_price')) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        if ($inStock = $request->query('in_stock')) {
            if ($inStock === 'true' || $inStock === '1') {
                $query->inStock();
            }
        }

        // Sıralama
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        $allowedSorts = ['name', 'price', 'created_at', 'stock'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtoupper($sortOrder));
        }

        // Pagination
        $offset = ($page - 1) * $perPage;
        $total = $query->count();
        $products = $query->limit($perPage)->offset($offset)->get();

        return new JsonResponse([
            'data' => $products->map(fn($p) => $p->toApiResponse())->toArray(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    // GET /api/v1/products/{id}
    public function show(int $id): JsonResponse {
        $cacheKey = "product_api_{$id}";

        $product = cache()->remember($cacheKey, 600, function() use ($id) {
            return Product::with(['category'])->find($id);
        });

        if (!$product) {
            return new JsonResponse([
                'error' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND',
            ], 404);
        }

        return new JsonResponse([
            'data' => $product->toApiResponse(),
        ]);
    }

    // POST /api/v1/products (Admin only)
    public function store(Request $request): JsonResponse {
        // Validate
        $validator = validator($request->all(), [
            'sku' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        if (!$validator->passes()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check duplicate SKU
        if (Product::where('sku', '=', $request->input('sku'))->exists()) {
            return new JsonResponse([
                'error' => 'SKU already exists',
                'code' => 'DUPLICATE_SKU',
            ], 409);
        }

        $data = $request->only([
            'sku', 'name', 'description', 'price', 'cost',
            'stock', 'category_id', 'images', 'metadata'
        ]);

        $product = Product::create($data);

        logger()->info('Product created via API', [
            'product_id' => $product->id,
            'user_id' => $request->getAttribute('user')->id,
        ]);

        return new JsonResponse([
            'data' => $product->toApiResponse(),
        ], 201);
    }

    // PUT /api/v1/products/{id} (Admin only)
    public function update(int $id, Request $request): JsonResponse {
        $product = Product::find($id);

        if (!$product) {
            return new JsonResponse([
                'error' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND',
            ], 404);
        }

        $data = $request->only([
            'name', 'description', 'price', 'cost',
            'stock', 'status', 'category_id', 'images', 'metadata'
        ]);

        $product->update($data);

        // Cache invalidate
        cache()->delete("product_api_{$id}");

        logger()->info('Product updated via API', [
            'product_id' => $product->id,
            'user_id' => $request->getAttribute('user')->id,
        ]);

        return new JsonResponse([
            'data' => $product->toApiResponse(),
        ]);
    }

    // DELETE /api/v1/products/{id} (Admin only)
    public function destroy(int $id): JsonResponse {
        $product = Product::find($id);

        if (!$product) {
            return new JsonResponse([
                'error' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND',
            ], 404);
        }

        // Check if product is in any orders
        $orderItems = OrderItem::where('product_id', '=', $id)->count();

        if ($orderItems > 0) {
            return new JsonResponse([
                'error' => 'Cannot delete product with existing orders',
                'code' => 'PRODUCT_HAS_ORDERS',
            ], 409);
        }

        $product->delete();

        cache()->delete("product_api_{$id}");

        return new JsonResponse(null, 204);
    }
}
```

### OrderController

```php
// app/Controllers/API/V1/OrderController.php
namespace App\Controllers\API\V1;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class OrderController {
    // GET /api/v1/orders (User's own orders)
    public function index(Request $request): JsonResponse {
        $user = $request->getAttribute('user');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $query = Order::where('user_id', '=', $user->id);

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', '=', $status);
        }

        $offset = ($page - 1) * $perPage;
        $total = $query->count();
        $orders = $query->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return new JsonResponse([
            'data' => $orders->map(fn($o) => $o->toApiResponse())->toArray(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    // GET /api/v1/orders/{id}
    public function show(int $id, Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        $order = Order::with(['items'])->find($id);

        if (!$order) {
            return new JsonResponse([
                'error' => 'Order not found',
                'code' => 'ORDER_NOT_FOUND',
            ], 404);
        }

        // Ownership check
        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return new JsonResponse([
                'error' => 'Forbidden',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        return new JsonResponse([
            'data' => array_merge($order->toApiResponse(), [
                'items' => $order->items()->get()->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ])->toArray(),
            ]),
        ]);
    }

    // POST /api/v1/orders
    public function store(Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        // Validate
        $validator = validator($request->all(), [
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|array',
        ]);

        if (!$validator->passes()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $items = $request->input('items');
        $subtotal = 0;
        $orderItems = [];

        // Process items and calculate total
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            if (!$product) {
                return new JsonResponse([
                    'error' => "Product {$item['product_id']} not found",
                    'code' => 'PRODUCT_NOT_FOUND',
                ], 404);
            }

            if (!$product->isAvailable()) {
                return new JsonResponse([
                    'error' => "{$product->name} is not available",
                    'code' => 'PRODUCT_NOT_AVAILABLE',
                ], 409);
            }

            if ($product->stock < $item['quantity']) {
                return new JsonResponse([
                    'error' => "Insufficient stock for {$product->name}",
                    'code' => 'INSUFFICIENT_STOCK',
                ], 409);
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
        $tax = $subtotal * 0.18; // 18% KDV
        $shipping = 50.00; // Sabit kargo
        $total = $subtotal + $tax + $shipping;

        // Create order
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $user->id,
            'status' => 'pending',
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $total,
            'shipping_address' => $request->input('shipping_address'),
            'billing_address' => $request->input('billing_address'),
            'notes' => $request->input('notes'),
        ]);

        // Create order items and decrement stock
        foreach ($orderItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product']->id,
                'product_name' => $item['product']->name,
                'product_sku' => $item['product']->sku,
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['subtotal'],
            ]);

            $item['product']->decrementStock($item['quantity']);
        }

        logger()->info('Order created via API', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'total' => $total,
        ]);

        // Send confirmation email (queue)
        \App\Jobs\SendOrderConfirmationEmail::dispatch($order->id);

        return new JsonResponse([
            'data' => $order->toApiResponse(),
        ], 201);
    }

    // PUT /api/v1/orders/{id}/cancel
    public function cancel(int $id, Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        $order = Order::find($id);

        if (!$order) {
            return new JsonResponse([
                'error' => 'Order not found',
                'code' => 'ORDER_NOT_FOUND',
            ], 404);
        }

        // Ownership check
        if ($order->user_id !== $user->id) {
            return new JsonResponse([
                'error' => 'Forbidden',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        if (!$order->canBeCancelled()) {
            return new JsonResponse([
                'error' => 'Order cannot be cancelled',
                'code' => 'CANNOT_CANCEL',
            ], 409);
        }

        $order->update(['status' => 'cancelled']);

        // Restore stock
        foreach ($order->items()->get() as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->stock += $item->quantity;
                $product->save();
            }
        }

        logger()->info('Order cancelled via API', [
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        return new JsonResponse([
            'data' => $order->toApiResponse(),
        ]);
    }
}
```

### AuthController

```php
// app/Controllers/API/V1/AuthController.php
namespace App\Controllers\API\V1;

use App\Models\User;
use App\Models\ApiToken;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class AuthController {
    // POST /api/v1/auth/register
    public function register(Request $request): JsonResponse {
        // Validate
        $validator = validator($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        if (!$validator->passes()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check duplicate email
        if (User::where('email', '=', $request->input('email'))->exists()) {
            return new JsonResponse([
                'error' => 'Email already exists',
                'code' => 'DUPLICATE_EMAIL',
            ], 409);
        }

        // Create user
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => password_hash($request->input('password'), PASSWORD_DEFAULT),
        ]);

        // Generate token
        $tokenModel = ApiToken::generate($user->id, 'default');
        $plainToken = $tokenModel->token; // Return plain token (only shown once)

        logger()->info('User registered via API', ['user_id' => $user->id]);

        return new JsonResponse([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $plainToken,
            ],
        ], 201);
    }

    // POST /api/v1/auth/login
    public function login(Request $request): JsonResponse {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return new JsonResponse([
                'error' => 'Email and password required',
                'code' => 'MISSING_CREDENTIALS',
            ], 400);
        }

        $user = User::where('email', '=', $email)->first();

        if (!$user || !password_verify($password, $user->password)) {
            return new JsonResponse([
                'error' => 'Invalid credentials',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        // Generate new token
        $tokenModel = ApiToken::generate($user->id, 'login-' . time());
        $plainToken = $tokenModel->token;

        logger()->info('User logged in via API', ['user_id' => $user->id]);

        return new JsonResponse([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $plainToken,
            ],
        ]);
    }

    // GET /api/v1/auth/me
    public function me(Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        return new JsonResponse([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    // POST /api/v1/auth/logout
    public function logout(Request $request): JsonResponse {
        $token = $request->getAttribute('api_token');

        if ($token) {
            $token->delete();
        }

        return new JsonResponse([
            'message' => 'Logged out successfully',
        ]);
    }
}
```

---

## 4. Middleware

### API Authentication Middleware

```php
// app/Middleware/ApiAuthMiddleware.php
namespace App\Middleware;

use App\Models\ApiToken;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class ApiAuthMiddleware {
    public function handle(Request $request, callable $next) {
        $authHeader = $request->getHeader('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse([
                'error' => 'Unauthorized',
                'code' => 'MISSING_TOKEN',
            ], 401);
        }

        $token = substr($authHeader, 7); // Remove "Bearer "
        $hashedToken = hash('sha256', $token);

        $apiToken = ApiToken::where('token', '=', $hashedToken)->first();

        if (!$apiToken) {
            return new JsonResponse([
                'error' => 'Invalid token',
                'code' => 'INVALID_TOKEN',
            ], 401);
        }

        if ($apiToken->isExpired()) {
            return new JsonResponse([
                'error' => 'Token expired',
                'code' => 'TOKEN_EXPIRED',
            ], 401);
        }

        // Update last used
        $apiToken->update(['last_used_at' => time()]);

        // Load user
        $user = $apiToken->user()->first();

        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found',
                'code' => 'USER_NOT_FOUND',
            ], 401);
        }

        // Attach to request
        $request->setAttribute('user', $user);
        $request->setAttribute('api_token', $apiToken);

        return $next($request);
    }
}
```

---

## 5. Routes

```php
// routes/api.php
use Conduit\Routing\Router;

$router = app(Router::class);

// API v1 routes
$router->group(['prefix' => 'api/v1'], function($router) {

    // Public routes
    $router->post('/auth/register', 'API\V1\AuthController@register');
    $router->post('/auth/login', 'API\V1\AuthController@login');

    // Public product browsing
    $router->get('/products', 'API\V1\ProductController@index');
    $router->get('/products/{id}', 'API\V1\ProductController@show');

    // Protected routes
    $router->group(['middleware' => 'api-auth'], function($router) {
        // Auth
        $router->get('/auth/me', 'API\V1\AuthController@me');
        $router->post('/auth/logout', 'API\V1\AuthController@logout');

        // Orders
        $router->get('/orders', 'API\V1\OrderController@index');
        $router->get('/orders/{id}', 'API\V1\OrderController@show');
        $router->post('/orders', 'API\V1\OrderController@store')
            ->middleware('throttle:10,60'); // 10 orders per minute max
        $router->put('/orders/{id}/cancel', 'API\V1\OrderController@cancel');

        // Admin only routes
        $router->group(['middleware' => 'role:admin'], function($router) {
            $router->post('/products', 'API\V1\ProductController@store');
            $router->put('/products/{id}', 'API\V1\ProductController@update');
            $router->delete('/products/{id}', 'API\V1\ProductController@destroy');
        });
    });
});
```

---

## 6. Error Handling

### API Exception Handler

```php
// app/Exceptions/ApiExceptionHandler.php
namespace App\Exceptions;

use Conduit\Http\JsonResponse;

class ApiExceptionHandler {
    public function handle(\Throwable $e): JsonResponse {
        $statusCode = 500;
        $error = [
            'error' => 'Internal server error',
            'code' => 'INTERNAL_ERROR',
        ];

        if (env('APP_DEBUG', false)) {
            $error['debug'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        // Log error
        logger()->error('API error', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return new JsonResponse($error, $statusCode);
    }
}
```

---

## 7. Rate Limiting Yapılandırması

```php
// config/rate-limiting.php (özel API limitleri)
return [
    'api' => [
        'enabled' => true,
        'driver' => 'database',

        'limits' => [
            // Global API limit
            'global' => [
                'max_attempts' => 1000,
                'decay_minutes' => 60,
            ],

            // Per-endpoint limits
            'orders.create' => [
                'max_attempts' => 10,
                'decay_minutes' => 60,
            ],

            'products.create' => [
                'max_attempts' => 100,
                'decay_minutes' => 60,
            ],
        ],
    ],
];
```

---

## 8. Testing

```php
// tests/Feature/API/ProductApiTest.php
namespace Tests\Feature\API;

use Tests\TestCase;
use App\Models\User;
use App\Models\ApiToken;
use App\Models\Product;

class ProductApiTest extends TestCase {
    private string $token;
    private User $user;

    public function setUp(): void {
        parent::setUp();

        $this->user = User::factory()->create();
        $tokenModel = ApiToken::generate($this->user->id);
        $this->token = $tokenModel->token;
    }

    public function testCanListProducts() {
        Product::factory()->count(5)->create();

        $response = $this->get('/api/v1/products');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
    }

    public function testCannotCreateProductWithoutAuth() {
        $response = $this->post('/api/v1/products', [
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testCanCreateProductAsAdmin() {
        $this->user->assignRole('admin');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->post('/api/v1/products', [
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertEquals('TEST-001', $data['data']['sku']);
    }

    public function testPaginationWorks() {
        Product::factory()->count(50)->create();

        $response = $this->get('/api/v1/products?page=2&per_page=10');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertEquals(2, $data['meta']['page']);
        $this->assertEquals(10, $data['meta']['per_page']);
        $this->assertEquals(50, $data['meta']['total']);
    }
}
```

```php
// tests/Feature/API/OrderApiTest.php
class OrderApiTest extends TestCase {
    public function testCanCreateOrder() {
        $user = User::factory()->create();
        $token = ApiToken::generate($user->id)->token;

        $product = Product::factory()->create([
            'price' => 100.00,
            'stock' => 10,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->post('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'shipping_address' => [
                'name' => 'John Doe',
                'address' => '123 Main St',
                'city' => 'Istanbul',
            ],
        ]);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertNotNull($data['data']['order_number']);
        $this->assertEquals('pending', $data['data']['status']);
    }

    public function testCannotOrderOutOfStockProduct() {
        $user = User::factory()->create();
        $token = ApiToken::generate($user->id)->token;

        $product = Product::factory()->create([
            'price' => 100.00,
            'stock' => 1,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->post('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
            'shipping_address' => ['name' => 'Test'],
        ]);

        $this->assertEquals(409, $response->getStatusCode());
    }
}
```

---

## 9. Postman Collection Örneği

```json
{
  "info": {
    "name": "E-commerce API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Auth",
      "item": [
        {
          "name": "Register",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/api/v1/auth/register",
            "body": {
              "mode": "raw",
              "raw": "{\n  \"name\": \"John Doe\",\n  \"email\": \"john@example.com\",\n  \"password\": \"password123\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        },
        {
          "name": "Login",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/api/v1/auth/login",
            "body": {
              "mode": "raw",
              "raw": "{\n  \"email\": \"john@example.com\",\n  \"password\": \"password123\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        }
      ]
    },
    {
      "name": "Products",
      "item": [
        {
          "name": "List Products",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/api/v1/products?page=1&per_page=20&search=laptop"
          }
        },
        {
          "name": "Create Product",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/api/v1/products",
            "header": [
              {
                "key": "Authorization",
                "value": "Bearer {{token}}"
              }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n  \"sku\": \"LAPTOP-001\",\n  \"name\": \"MacBook Pro\",\n  \"price\": 29999.99,\n  \"stock\": 5\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        }
      ]
    }
  ]
}
```

---

## 10. Özet

Bu API Backend şunları içerir:

- ✅ **RESTful Design**: Standart HTTP metodları ve status kodları
- ✅ **Authentication**: Bearer token based (API tokens)
- ✅ **Authorization**: Role-based access control
- ✅ **Rate Limiting**: Endpoint bazlı limitler
- ✅ **Pagination**: Offset-based pagination
- ✅ **Filtering & Sorting**: Query parameters ile
- ✅ **Validation**: Input validation ve hata mesajları
- ✅ **Error Handling**: Tutarlı error responses
- ✅ **Logging**: Tüm önemli işlemler loglanır
- ✅ **Caching**: Sık kullanılan data cache'lenir
- ✅ **Testing**: Comprehensive test coverage
- ✅ **Documentation**: API endpoint documentation

**Çalıştırma:**
```bash
php conduit migrate
php conduit queue:work & # Background worker
php -S localhost:8000 -t public # Development server
```

**API Kullanımı:**
```bash
# Register
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John","email":"john@example.com","password":"pass123"}'

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"pass123"}'

# List products
curl http://localhost:8000/api/v1/products

# Create order
curl -X POST http://localhost:8000/api/v1/orders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"items":[{"product_id":1,"quantity":2}],"shipping_address":{"name":"John","address":"123 St"}}'
```
