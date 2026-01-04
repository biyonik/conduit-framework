# Container & Dependency Injection (DI)

## İçindekiler
1. [Giriş](#giriş)
2. [Temel Kullanım](#temel-kullanım)
3. [Binding Tipleri](#binding-tipleri)
4. [Otomatik Çözümleme](#otomatik-çözümleme)
5. [Gerçek Dünya Örnekleri](#gerçek-dünya-örnekleri)

---

## Giriş

Container (Dependency Injection Container), framework'ün kalbidir. Sınıflar arası bağımlılıkları otomatik olarak çözer ve yönetir.

### Neden Önemli?
- ✅ Loose coupling (gevşek bağlılık)
- ✅ Test edilebilirlik
- ✅ Kod tekrarını önler
- ✅ Merkezi konfigürasyon

---

## Temel Kullanım

### Container'a Erişim

```php
// Uygulama container'ı
$container = app();

// Veya doğrudan Application instance
use Conduit\Core\Application;
$app = Application::getInstance();
$container = $app->getContainer();
```

### Basit Binding

```php
// Bir sınıfı kaydet
$container->bind(MyService::class, function($container) {
    return new MyService();
});

// Kullan
$service = $container->make(MyService::class);
```

### Singleton Binding

```php
// Singleton olarak kaydet (tek instance)
$container->singleton(Database::class, function($container) {
    return new Database([
        'host' => 'localhost',
        'database' => 'myapp',
    ]);
});

// Her çağrıda aynı instance döner
$db1 = $container->make(Database::class);
$db2 = $container->make(Database::class);
// $db1 === $db2 (true)
```

---

## Binding Tipleri

### 1. Basit Binding (Transient)

Her çağrıda yeni instance oluşturur.

```php
$container->bind(Logger::class, function($container) {
    return new Logger('/var/log/app.log');
});

$logger1 = app(Logger::class);
$logger2 = app(Logger::class);
// $logger1 !== $logger2 (farklı instance'lar)
```

### 2. Singleton Binding

Tek bir instance, tüm uygulama boyunca paylaşılır.

```php
$container->singleton(CacheManager::class, function($container) {
    return new CacheManager(config('cache'));
});

// Her yerde aynı instance
$cache = app(CacheManager::class);
```

### 3. Instance Binding

Var olan bir instance'ı kaydet.

```php
$config = new Config(['app_name' => 'MyApp']);
$container->instance(Config::class, $config);

// Aynı instance döner
$retrievedConfig = app(Config::class);
```

### 4. Interface Binding

Interface'i concrete class'a bağla.

```php
// Interface tanımı
interface PaymentGateway {
    public function charge(float $amount): bool;
}

// Concrete implementation
class StripeGateway implements PaymentGateway {
    public function charge(float $amount): bool {
        // Stripe ile ödeme al
        return true;
    }
}

// Interface'i concrete class'a bağla
$container->bind(PaymentGateway::class, StripeGateway::class);

// Interface iste, concrete class al
$gateway = app(PaymentGateway::class); // StripeGateway instance'ı
```

---

## Otomatik Çözümleme

Container, constructor'daki type-hint'lere bakarak bağımlılıkları otomatik çözer.

### Basit Örnek

```php
class EmailService {
    public function __construct(
        private Logger $logger,
        private MailerInterface $mailer
    ) {}

    public function send(string $to, string $subject, string $body): void {
        $this->logger->info("Sending email to {$to}");
        $this->mailer->send($to, $subject, $body);
    }
}

// Container otomatik çözer
$emailService = app(EmailService::class);
// Logger ve MailerInterface otomatik inject edilir
```

### Nested Dependencies

```php
class OrderService {
    public function __construct(
        private Database $db,
        private EmailService $emailService,
        private PaymentGateway $payment
    ) {}
}

// Container tüm bağımlılık ağacını otomatik çözer
$orderService = app(OrderService::class);
// Database, EmailService, PaymentGateway otomatik inject edilir
// EmailService içindeki Logger ve Mailer de otomatik inject edilir
```

---

## Gerçek Dünya Örnekleri

### Örnek 1: Database Bağlantısı (Singleton)

```php
// Service Provider içinde (boot edilirken)
$container->singleton(Database::class, function($container) {
    $config = require base_path('config/database.php');

    return new Database([
        'driver' => $config['driver'],
        'host' => $config['host'],
        'database' => $config['database'],
        'username' => $config['username'],
        'password' => $config['password'],
    ]);
});

// Kullanım - her yerde aynı bağlantı
class UserRepository {
    public function __construct(private Database $db) {}

    public function find(int $id): ?User {
        return $this->db->table('users')->find($id);
    }
}

$userRepo = app(UserRepository::class); // Database otomatik inject edilir
```

### Örnek 2: Payment Gateway Değiştirme

```php
// config/payment.php
return [
    'gateway' => env('PAYMENT_GATEWAY', 'stripe'), // stripe, paypal, veya test
];

// Service Provider
$container->bind(PaymentGateway::class, function($container) {
    $gateway = config('payment.gateway');

    return match($gateway) {
        'stripe' => new StripeGateway(config('payment.stripe_key')),
        'paypal' => new PayPalGateway(config('payment.paypal_key')),
        'test' => new TestPaymentGateway(), // Development için
        default => throw new \Exception("Unknown payment gateway: {$gateway}"),
    };
});

// Controller'da kullanım
class CheckoutController {
    public function __construct(private PaymentGateway $payment) {}

    public function process(Request $request): Response {
        $amount = $request->input('amount');

        if ($this->payment->charge($amount)) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false], 400);
    }
}
```

### Örnek 3: Repository Pattern

```php
// Repository interface
interface UserRepositoryInterface {
    public function find(int $id): ?User;
    public function create(array $data): User;
    public function all(): array;
}

// Concrete implementation
class DatabaseUserRepository implements UserRepositoryInterface {
    public function __construct(private Database $db) {}

    public function find(int $id): ?User {
        return $this->db->table('users')->find($id);
    }

    public function create(array $data): User {
        $id = $this->db->table('users')->insert($data);
        return $this->find($id);
    }

    public function all(): array {
        return $this->db->table('users')->get();
    }
}

// Service Provider'da bind et
$container->bind(UserRepositoryInterface::class, DatabaseUserRepository::class);

// Controller'da kullan
class UserController {
    public function __construct(private UserRepositoryInterface $users) {}

    public function index(): JsonResponse {
        return new JsonResponse($this->users->all());
    }

    public function show(int $id): JsonResponse {
        $user = $this->users->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        return new JsonResponse($user);
    }
}
```

### Örnek 4: Service Class'ları

```php
// Bir sipariş işleme servisi
class OrderProcessingService {
    public function __construct(
        private OrderRepository $orders,
        private PaymentGateway $payment,
        private EmailService $email,
        private Logger $logger
    ) {}

    public function process(Order $order): bool {
        $this->logger->info("Processing order #{$order->id}");

        try {
            // Ödeme al
            if (!$this->payment->charge($order->total)) {
                throw new \Exception('Payment failed');
            }

            // Siparişi güncelle
            $this->orders->update($order->id, [
                'status' => 'paid',
                'paid_at' => time(),
            ]);

            // Email gönder
            $this->email->send(
                $order->customer_email,
                'Siparişiniz Alındı',
                "Sipariş #${order->id} başarıyla alındı."
            );

            $this->logger->info("Order #{$order->id} processed successfully");

            return true;

        } catch (\Exception $e) {
            $this->logger->error("Order processing failed", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

// Controller'da kullanım - tüm bağımlılıklar otomatik inject edilir
class OrderController {
    public function __construct(private OrderProcessingService $processor) {}

    public function checkout(Request $request): Response {
        $order = Order::find($request->input('order_id'));

        if ($this->processor->process($order)) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false], 400);
    }
}
```

### Örnek 5: Alias (Takma İsim) Kullanımı

```php
// Service Provider'da
$container->singleton(CacheManager::class, function($container) {
    return new CacheManager(config('cache'));
});

// Alias tanımla
$container->alias(CacheManager::class, 'cache');

// Şimdi iki şekilde de erişebilirsin
$cache1 = app(CacheManager::class);
$cache2 = app('cache');
// $cache1 === $cache2 (aynı instance)

// Veya helper function
$cache3 = cache();
// $cache1 === $cache3 (aynı instance)
```

---

## Array Access Kullanımı

Container, ArrayAccess implement eder:

```php
// Normal yöntem
$container->bind('database', fn() => new Database());
$db = $container->make('database');

// Array syntax
$container['database'] = fn() => new Database();
$db = $container['database'];

// Kontrol et
if (isset($container['database'])) {
    // Database kayıtlı
}
```

---

## Best Practices

### ✅ YAP

```php
// 1. Interface'lere program yap
$container->bind(MailerInterface::class, SmtpMailer::class);

// 2. Singleton'ları bilinçli kullan
$container->singleton(Database::class, ...); // Bağlantı pooling için iyi

// 3. Service Provider kullan
class MyServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->container->bind(...);
    }
}

// 4. Type-hint kullan
class MyController {
    public function __construct(private Database $db) {} // ✅ İyi
}
```

### ❌ YAPMA

```php
// 1. Concrete class'lara hard-code bağımlılık
class MyController {
    private $db;

    public function __construct() {
        $this->db = new Database(); // ❌ Kötü - test edilemez
    }
}

// 2. Her şeyi singleton yapma
$container->singleton(UserController::class, ...); // ❌ Controller singleton olmamalı

// 3. Container'ı her yerde kullanma
class MyClass {
    public function doSomething() {
        $db = app(Database::class); // ❌ Service Locator pattern - anti-pattern
    }
}
// Bunun yerine constructor injection kullan
```

---

## Troubleshooting

### Hata: "Class X does not exist"

```php
// Sebep: Class dosyası yüklenemedi
// Çözüm: Namespace ve dosya yolunu kontrol et
```

### Hata: "Circular dependency detected"

```php
// Sebep: A -> B -> A gibi döngüsel bağımlılık
class A {
    public function __construct(B $b) {}
}

class B {
    public function __construct(A $a) {} // ❌ Circular!
}

// Çözüm: Interface veya Lazy Loading kullan
```

### Hata: "Cannot resolve parameter"

```php
// Sebep: Primitive type (string, int) inject edilemez
class MyClass {
    public function __construct(string $apiKey) {} // ❌ string resolve edilemez
}

// Çözüm: Closure ile değer ver
$container->bind(MyClass::class, fn() => new MyClass('my-api-key'));
```

---

## Özet

Container kullanımı:
1. ✅ **Bind** - Sınıfları kaydet
2. ✅ **Make** - Sınıfları oluştur
3. ✅ **Type-hint** - Otomatik injection
4. ✅ **Interface** - Gevşek bağlılık
5. ✅ **Singleton** - Paylaşılan instance'lar

**Altın Kural:** Constructor'da ne istersen container otomatik verir!
