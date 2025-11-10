# Conduit PHP Framework

**Production-ready, API-first PHP framework designed for shared hosting environments**

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

---

## 🎯 Vision

Conduit PHP is a modern, lightweight framework built specifically for API development on shared hosting environments. While powerful frameworks like Laravel and Symfony are excellent, they often require VPS or dedicated server capabilities. Conduit fills the gap by providing:

- ✅ **Shared Hosting Compatible** - No Redis, no special PHP extensions, just works
- ✅ **API-First Design** - Built for RESTful APIs from the ground up
- ✅ **Production Ready** - Security, performance, and scalability baked in
- ✅ **PSR Compliant** - Follows PHP-FIG standards (PSR-4, PSR-7, PSR-11, PSR-16)
- ✅ **Zero Config** - Works out of the box with sensible defaults
- ✅ **Minimal Dependencies** - Only requires PHP 8.0+ and PDO

---

## 🚀 Features

### Core Features
- **PSR-7 HTTP Messages** - Standard request/response handling
- **PSR-11 Container** - Dependency injection container with auto-resolution
- **RESTful Routing** - Elegant route definitions with middleware support
- **Query Builder** - Fluent, expressive database queries
- **Active Record ORM** - Eloquent-like model relationships
- **Migration System** - Database version control
- **JWT Authentication** - Stateless authentication for APIs
- **Role-Based Access Control (RBAC)** - Fine-grained authorization
- **Comprehensive Validation** - 30+ built-in validation rules
- **File & Database Cache** - PSR-16 compliant caching

### Security Features
- SQL Injection prevention (Prepared Statements)
- XSS protection (Output escaping)
- CSRF protection
- Rate limiting (Brute-force prevention)
- Bcrypt/Argon2 password hashing
- AES-256-GCM encryption
- CORS middleware
- Secure session handling

### Performance Features
- OPcache optimization
- Route caching
- Config caching
- Query result caching
- Lazy loading
- Connection pooling

---

## 📋 Requirements

- PHP >= 8.0
- PDO Extension
- JSON Extension
- Mbstring Extension
- OpenSSL Extension
- MySQL 5.7+ / MariaDB 10.3+ / SQLite 3.8+

**Note:** No Redis, Memcached, or any other special services required!

---

## 📦 Installation

### 1. Via Composer (Recommended)

```bash
composer create-project conduit/framework my-api
cd my-api
```

### 2. Manual Installation

```bash
git clone https://github.com/yourusername/conduit-framework.git my-api
cd my-api
composer install
```

### 3. Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php conduit key:generate

# Configure database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_DATABASE=your_database
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Run migrations
php conduit migrate
```

### 4. Start Development Server

```bash
composer serve
# or
php -S localhost:8000 -t public
```

Visit: `http://localhost:8000/api/health`

---

## 🏗️ Project Structure

```
conduit/
├── app/                      # Your application code
│   ├── Controllers/         # HTTP request handlers
│   ├── Services/            # Business logic
│   ├── Repositories/        # Data access layer
│   ├── Models/              # Database models
│   ├── Middleware/          # Custom middleware
│   └── Validators/          # Validation rules
├── bootstrap/               # Framework bootstrap
├── config/                  # Configuration files
├── database/
│   └── migrations/         # Database migrations
├── public/                  # Web root (document root)
│   └── index.php           # Entry point
├── routes/
│   └── api.php             # API routes
├── src/                     # Framework core
│   ├── Core/               # Application, Container
│   ├── Http/               # Request, Response
│   ├── Routing/            # Router, Routes
│   ├── Database/           # Query Builder, ORM
│   ├── Cache/              # Cache drivers
│   ├── Security/           # Auth, Encryption
│   └── Validation/         # Validator
├── storage/                 # File storage
│   ├── cache/              # File cache
│   ├── logs/               # Log files
│   └── sessions/           # Session files
└── tests/                   # Unit tests
```

---

## 📖 Quick Start Guide

### 1. Define Routes

```php
// routes/api.php
use Conduit\Routing\Router;

$router = app(Router::class);

// Basic route
$router->get('/users', 'UserController@index');

// Route with parameter
$router->get('/users/{id}', 'UserController@show');

// Protected route with middleware
$router->post('/posts', 'PostController@store')
    ->middleware('auth');

// Route group
$router->group(['prefix' => '/api/v1', 'middleware' => 'auth'], function($router) {
    $router->resource('posts', 'PostController');
});
```

### 2. Create Controller

```php
// app/Controllers/UserController.php
namespace App\Controllers;

use App\Services\UserService;
use Conduit\Http\Request;

class UserController extends BaseController
{
    public function __construct(
        private UserService $userService
    ) {}
    
    public function index(Request $request)
    {
        $users = $this->userService->getAllUsers();
        
        return $this->success($users);
    }
    
    public function show(int $id)
    {
        $user = $this->userService->findUser($id);
        
        if (!$user) {
            return $this->error('User not found', 404);
        }
        
        return $this->success($user);
    }
}
```

### 3. Create Service (Business Logic)

```php
// app/Services/UserService.php
namespace App\Services;

use App\Repositories\UserRepository;

class UserService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}
    
    public function getAllUsers()
    {
        return $this->userRepository->all();
    }
    
    public function findUser(int $id)
    {
        return $this->userRepository->find($id);
    }
}
```

### 4. Create Model

```php
// app/Models/User.php
namespace App\Models;

use Conduit\Database\Model;

class User extends Model
{
    protected $table = 'users';
    
    protected $fillable = ['name', 'email', 'password'];
    
    protected $hidden = ['password'];
    
    // Relationships
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

---

## 🔐 Authentication Example

### Register User

```php
POST /api/register
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123",
    "password_confirmation": "secret123"
}

Response:
{
    "success": true,
    "data": {
        "user": {...},
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "Bearer"
    }
}
```

### Login

```php
POST /api/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "secret123"
}

Response:
{
    "success": true,
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "Bearer",
        "expires_in": 3600
    }
}
```

### Access Protected Route

```php
GET /api/user
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...

Response:
{
    "success": true,
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
    }
}
```

---

## 🧪 Testing

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test
vendor/bin/phpunit tests/Unit/ContainerTest.php
```

---

## 🚀 Deployment (Shared Hosting)

### 1. Upload Files
Upload all files except `.git` and `node_modules` to your shared hosting.

### 2. Set Document Root
Point your domain to the `public` directory.

### 3. Configure .htaccess
The included `.htaccess` file should work on most shared hosts.

### 4. Set Environment
```bash
# Set to production
APP_ENV=production
APP_DEBUG=false

# Set cache driver
CACHE_DRIVER=file  # or database
```

### 5. Optimize
```bash
php conduit optimize
# This will:
# - Cache routes
# - Cache config
# - Enable OPcache preloading (if available)
```

---

## 📚 Documentation

Full documentation is available at: [docs.conduitphp.com](https://docs.conduitphp.com)

- [Getting Started](docs/getting-started.md)
- [Routing](docs/routing.md)
- [Controllers](docs/controllers.md)
- [Database](docs/database.md)
- [Authentication](docs/authentication.md)
- [Validation](docs/validation.md)
- [Cache](docs/cache.md)
- [Deployment](docs/deployment.md)

---

## 🤝 Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/yourusername/conduit-framework.git
cd conduit-framework
composer install
cp .env.example .env
php conduit key:generate
php conduit migrate
composer test
```

---

## 📄 License

The Conduit PHP Framework is open-sourced software licensed under the [MIT license](LICENSE).

---

## 🙏 Credits

Inspired by:
- [Laravel](https://laravel.com) - Elegant syntax and architecture
- [Symfony](https://symfony.com) - Components and best practices
- [Slim Framework](https://www.slimframework.com) - Simplicity and PSR compliance

---

## 💬 Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/conduit-framework/issues)
- **Discussions**: [GitHub Discussions](https://github.com/yourusername/conduit-framework/discussions)
- **Email**: support@conduitphp.com

---

## 🎯 Roadmap

- [ ] CLI Commands for scaffolding
- [ ] Queue system (database-based)
- [ ] WebSocket support
- [ ] GraphQL support
- [ ] Admin panel generator
- [ ] API documentation generator (OpenAPI/Swagger)
- [ ] Multi-tenancy support

---

**Built with ❤️ for developers who need a powerful API framework on shared hosting.**
