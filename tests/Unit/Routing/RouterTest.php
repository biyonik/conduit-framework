<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use Conduit\Core\Container;
use Conduit\Http\Request;
use Conduit\Routing\Router;
use Conduit\Routing\Route;
use Conduit\Routing\Exceptions\RouteNotFoundException;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    protected Router $router;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        $this->router = new Router($this->container);
    }

    // ==================== BASIC ROUTING TESTS ====================

    public function testGetRoute(): void
    {
        $route = $this->router->get('/users', fn() => 'users');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['GET', 'HEAD'], $route->getMethods());
        $this->assertEquals('/users', $route->getUri());
    }

    public function testPostRoute(): void
    {
        $route = $this->router->post('/users', fn() => 'create');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['POST'], $route->getMethods());
    }

    public function testPutRoute(): void
    {
        $route = $this->router->put('/users/1', fn() => 'update');

        $this->assertEquals(['PUT'], $route->getMethods());
    }

    public function testPatchRoute(): void
    {
        $route = $this->router->patch('/users/1', fn() => 'patch');

        $this->assertEquals(['PATCH'], $route->getMethods());
    }

    public function testDeleteRoute(): void
    {
        $route = $this->router->delete('/users/1', fn() => 'delete');

        $this->assertEquals(['DELETE'], $route->getMethods());
    }

    public function testOptionsRoute(): void
    {
        $route = $this->router->options('/users', fn() => 'options');

        $this->assertEquals(['OPTIONS'], $route->getMethods());
    }

    public function testAnyRoute(): void
    {
        $route = $this->router->any('/users', fn() => 'any');

        $this->assertEquals(Route::SUPPORTED_METHODS, $route->getMethods());
    }

    public function testMatchRoute(): void
    {
        $route = $this->router->match(['GET', 'POST'], '/users', fn() => 'match');

        $this->assertEquals(['GET', 'POST'], $route->getMethods());
    }

    // ==================== ROUTE PARAMETERS TESTS ====================

    public function testSimpleParameterMatching(): void
    {
        $this->router->get('/users/{id}', fn($id) => "user-{$id}");

        $request = $this->createRequest('GET', '/users/123');
        $route = $this->router->match($request);

        $this->assertNotNull($route);
        $this->assertEquals('123', $route->parameter('id'));
    }

    public function testMultipleParametersMatching(): void
    {
        $this->router->get('/users/{userId}/posts/{postId}', fn() => 'post');

        $request = $this->createRequest('GET', '/users/10/posts/20');
        $route = $this->router->match($request);

        $this->assertEquals('10', $route->parameter('userId'));
        $this->assertEquals('20', $route->parameter('postId'));
    }

    public function testOptionalParameter(): void
    {
        $this->router->get('/posts/{id?}', fn() => 'posts');

        // With parameter
        $request1 = $this->createRequest('GET', '/posts/123');
        $route1 = $this->router->match($request1);
        $this->assertEquals('123', $route1->parameter('id'));

        // Without parameter
        $request2 = $this->createRequest('GET', '/posts');
        $route2 = $this->router->match($request2);
        $this->assertNull($route2->parameter('id'));
    }

    public function testParameterConstraints(): void
    {
        $this->router->get('/users/{id}', fn($id) => "user-{$id}")
            ->where('id', '[0-9]+');

        // Valid: numeric ID
        $request1 = $this->createRequest('GET', '/users/123');
        $route1 = $this->router->match($request1);
        $this->assertNotNull($route1);

        // Invalid: non-numeric ID
        $this->expectException(RouteNotFoundException::class);
        $request2 = $this->createRequest('GET', '/users/abc');
        $this->router->match($request2);
    }

    public function testMultipleConstraints(): void
    {
        $this->router->get('/users/{id}/posts/{slug}', fn() => 'post')
            ->where([
                'id' => '[0-9]+',
                'slug' => '[a-z0-9-]+'
            ]);

        $request = $this->createRequest('GET', '/users/123/posts/my-post-title');
        $route = $this->router->match($request);

        $this->assertEquals('123', $route->parameter('id'));
        $this->assertEquals('my-post-title', $route->parameter('slug'));
    }

    // ==================== NAMED ROUTES TESTS ====================

    public function testNamedRoute(): void
    {
        $this->router->get('/users', fn() => 'users')
            ->name('users.index');

        $route = $this->router->getRouteByName('users.index');

        $this->assertNotNull($route);
        $this->assertEquals('users.index', $route->getName());
    }

    public function testRouteUrl(): void
    {
        $this->router->get('/users/{id}', fn() => 'user')
            ->name('users.show');

        $url = $this->router->route('users.show', ['id' => 123]);

        $this->assertEquals('/users/123', $url);
    }

    public function testRouteUrlWithMultipleParameters(): void
    {
        $this->router->get('/users/{userId}/posts/{postId}', fn() => 'post')
            ->name('users.posts.show');

        $url = $this->router->route('users.posts.show', [
            'userId' => 10,
            'postId' => 20
        ]);

        $this->assertEquals('/users/10/posts/20', $url);
    }

    // ==================== MIDDLEWARE TESTS ====================

    public function testRouteMiddleware(): void
    {
        $route = $this->router->get('/admin', fn() => 'admin')
            ->middleware('auth');

        $this->assertTrue($route->hasMiddleware('auth'));
    }

    public function testMultipleMiddleware(): void
    {
        $route = $this->router->get('/admin', fn() => 'admin')
            ->middleware(['auth', 'admin']);

        $this->assertTrue($route->hasMiddleware('auth'));
        $this->assertTrue($route->hasMiddleware('admin'));
    }

    public function testChainedMiddleware(): void
    {
        $route = $this->router->get('/admin', fn() => 'admin')
            ->middleware('auth')
            ->middleware('admin');

        $middleware = $route->getMiddleware();

        $this->assertCount(2, $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertContains('admin', $middleware);
    }

    // ==================== ROUTE GROUPS TESTS ====================

    public function testGroupPrefix(): void
    {
        $this->router->group(['prefix' => '/api'], function ($router) {
            $router->get('/users', fn() => 'users');
        });

        $request = $this->createRequest('GET', '/api/users');
        $route = $this->router->match($request);

        $this->assertEquals('/api/users', $route->getUri());
    }

    public function testGroupMiddleware(): void
    {
        $this->router->group(['middleware' => 'auth'], function ($router) {
            $router->get('/profile', fn() => 'profile');
        });

        $request = $this->createRequest('GET', '/profile');
        $route = $this->router->match($request);

        $this->assertTrue($route->hasMiddleware('auth'));
    }

    public function testNestedGroups(): void
    {
        $this->router->group(['prefix' => '/api', 'middleware' => 'api'], function ($router) {
            $router->group(['prefix' => '/v1', 'middleware' => 'throttle'], function ($router) {
                $router->get('/users', fn() => 'users');
            });
        });

        $request = $this->createRequest('GET', '/api/v1/users');
        $route = $this->router->match($request);

        $this->assertEquals('/api/v1/users', $route->getUri());
        $this->assertTrue($route->hasMiddleware('api'));
        $this->assertTrue($route->hasMiddleware('throttle'));
    }

    // ==================== RESOURCE ROUTING TESTS ====================

    public function testResourceRoutes(): void
    {
        $this->router->resource('users', 'UserController');

        $routes = $this->router->getRoutes();

        // Should create 7 RESTful routes
        $this->assertGreaterThanOrEqual(7, count($routes));

        // Test index route
        $request = $this->createRequest('GET', '/users');
        $route = $this->router->match($request);
        $this->assertEquals('/users', $route->getUri());

        // Test show route
        $request = $this->createRequest('GET', '/users/123');
        $route = $this->router->match($request);
        $this->assertEquals('123', $route->parameter('id'));

        // Test create route (form)
        $request = $this->createRequest('GET', '/users/create');
        $route = $this->router->match($request);
        $this->assertEquals('/users/create', $route->getUri());

        // Test store route
        $request = $this->createRequest('POST', '/users');
        $route = $this->router->match($request);
        $this->assertEquals('/users', $route->getUri());

        // Test edit route (form)
        $request = $this->createRequest('GET', '/users/123/edit');
        $route = $this->router->match($request);
        $this->assertEquals('123', $route->parameter('id'));

        // Test update route
        $request = $this->createRequest('PUT', '/users/123');
        $route = $this->router->match($request);
        $this->assertEquals('123', $route->parameter('id'));

        // Test delete route
        $request = $this->createRequest('DELETE', '/users/123');
        $route = $this->router->match($request);
        $this->assertEquals('123', $route->parameter('id'));
    }

    public function testApiResourceRoutes(): void
    {
        $this->router->apiResource('posts', 'PostController');

        // Should not have create/edit routes (API doesn't need forms)
        $request1 = $this->createRequest('GET', '/posts/create');
        $this->expectException(RouteNotFoundException::class);
        $this->router->match($request1);
    }

    // ==================== ROUTE MATCHING TESTS ====================

    public function testExactPathMatch(): void
    {
        $this->router->get('/users/profile', fn() => 'profile');
        $this->router->get('/users/{id}', fn() => 'show');

        // Should match exact path first
        $request = $this->createRequest('GET', '/users/profile');
        $route = $this->router->match($request);

        $this->assertEquals('/users/profile', $route->getUri());
    }

    public function testMethodNotAllowed(): void
    {
        $this->router->get('/users', fn() => 'index');

        $this->expectException(RouteNotFoundException::class);

        $request = $this->createRequest('POST', '/users');
        $this->router->match($request);
    }

    public function testRouteNotFound(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $request = $this->createRequest('GET', '/nonexistent');
        $this->router->match($request);
    }

    // ==================== ROUTE CACHING TESTS ====================

    public function testGetRoutes(): void
    {
        $this->router->get('/users', fn() => 'users');
        $this->router->post('/users', fn() => 'create');

        $routes = $this->router->getRoutes();

        $this->assertGreaterThanOrEqual(2, count($routes));
    }

    // ==================== CONTROLLER ROUTING TESTS ====================

    public function testControllerAction(): void
    {
        $route = $this->router->get('/users', 'UserController@index');

        $this->assertEquals('UserController@index', $route->getAction());
    }

    public function testInvokableController(): void
    {
        $route = $this->router->get('/home', 'HomeController');

        $this->assertEquals('HomeController', $route->getAction());
    }

    // ==================== DOMAIN ROUTING TESTS ====================

    public function testDomainRoute(): void
    {
        $route = $this->router->get('/admin', fn() => 'admin')
            ->domain('admin.example.com');

        $this->assertEquals('admin.example.com', $route->getDomain());
    }

    // ==================== DEFAULT VALUES TESTS ====================

    public function testDefaultParameterValue(): void
    {
        $this->router->get('/posts/{category?}', fn() => 'posts')
            ->defaults(['category' => 'all']);

        $request = $this->createRequest('GET', '/posts');
        $route = $this->router->match($request);

        $this->assertEquals('all', $route->parameter('category'));
    }

    // ==================== IMMUTABILITY TESTS ====================

    public function testRouteImmutability(): void
    {
        $route1 = $this->router->get('/users', fn() => 'users');
        $route2 = $route1->middleware('auth');

        $this->assertNotSame($route1, $route2);
        $this->assertFalse($route1->hasMiddleware('auth'));
        $this->assertTrue($route2->hasMiddleware('auth'));
    }

    // ==================== HELPER METHODS ====================

    protected function createRequest(string $method, string $uri): Request
    {
        return Request::create($uri, $method);
    }
}
