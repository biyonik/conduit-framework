<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Conduit\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    // ==================== BASIC REQUEST TESTS ====================

    public function testCreateFromGlobals(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users';

        $request = Request::createFromGlobals();

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/users', $request->getUri()->getPath());
    }

    public function testCreateRequest(): void
    {
        $request = Request::create('/users', 'POST');

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/users', $request->getUri()->getPath());
    }

    // ==================== INPUT HANDLING TESTS ====================

    public function testGetQueryParameter(): void
    {
        $request = Request::create('/search?q=test&page=1', 'GET');

        $this->assertEquals('test', $request->query('q'));
        $this->assertEquals('1', $request->query('page'));
        $this->assertNull($request->query('nonexistent'));
    }

    public function testGetQueryWithDefault(): void
    {
        $request = Request::create('/search', 'GET');

        $this->assertEquals('default', $request->query('missing', 'default'));
    }

    public function testGetAllQueryParameters(): void
    {
        $request = Request::create('/search?q=test&page=1&sort=asc', 'GET');

        $query = $request->query();

        $this->assertIsArray($query);
        $this->assertEquals('test', $query['q']);
        $this->assertEquals('1', $query['page']);
        $this->assertEquals('asc', $query['sort']);
    }

    public function testPostData(): void
    {
        $request = Request::create('/users', 'POST', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $this->assertEquals('John Doe', $request->input('name'));
        $this->assertEquals('john@example.com', $request->input('email'));
    }

    public function testJsonInput(): void
    {
        $json = json_encode(['name' => 'John', 'email' => 'john@example.com']);

        $request = Request::create('/api/users', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $json);

        $this->assertEquals('John', $request->input('name'));
        $this->assertEquals('john@example.com', $request->input('email'));
    }

    // ==================== HEADERS TESTS ====================

    public function testGetHeader(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'TestBot/1.0',
            'HTTP_ACCEPT' => 'application/json'
        ]);

        $this->assertEquals('TestBot/1.0', $request->header('User-Agent'));
        $this->assertEquals('application/json', $request->header('Accept'));
    }

    public function testBearerToken(): void
    {
        $request = Request::create('/api/users', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token-123'
        ]);

        $this->assertEquals('test-token-123', $request->bearerToken());
    }

    public function testBearerTokenMissing(): void
    {
        $request = Request::create('/api/users', 'GET');

        $this->assertNull($request->bearerToken());
    }

    // ==================== IP ADDRESS TESTS ====================

    public function testIpAddressWithoutProxy(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100'
        ]);

        $this->assertEquals('192.168.1.100', $request->ip());
    }

    public function testIpAddressWithTrustedProxy(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1', // Proxy IP
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1' // Client IP
        ]);

        // Configure trusted proxies
        $request->setTrustedProxies(['10.0.0.1']);

        $this->assertEquals('203.0.113.1', $request->ip());
    }

    public function testIpAddressRejectsUntrustedProxy(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.50', // Untrusted IP
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1' // Spoofed client IP
        ]);

        // No trusted proxies configured
        $ip = $request->ip();

        // Should return REMOTE_ADDR, not the forwarded IP
        $this->assertEquals('192.168.1.50', $ip);
    }

    public function testIpAddressWithMultipleForwardedIps(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1, 198.51.100.1' // Multiple IPs
        ]);

        $request->setTrustedProxies(['10.0.0.1']);

        // Should return first IP (original client)
        $this->assertEquals('203.0.113.1', $request->ip());
    }

    // ==================== METHOD TESTS ====================

    public function testIsMethod(): void
    {
        $request = Request::create('/test', 'POST');

        $this->assertTrue($request->isMethod('POST'));
        $this->assertFalse($request->isMethod('GET'));
    }

    public function testIsMethodCaseInsensitive(): void
    {
        $request = Request::create('/test', 'POST');

        $this->assertTrue($request->isMethod('post'));
        $this->assertTrue($request->isMethod('POST'));
        $this->assertTrue($request->isMethod('Post'));
    }

    // ==================== CONTENT TYPE TESTS ====================

    public function testIsJson(): void
    {
        $request = Request::create('/api/users', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json'
        ]);

        $this->assertTrue($request->isJson());
    }

    public function testExpectsJson(): void
    {
        $request = Request::create('/api/users', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json'
        ]);

        $this->assertTrue($request->expectsJson());
    }

    public function testWantsJson(): void
    {
        $request = Request::create('/api/users', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);

        $this->assertTrue($request->wantsJson());
    }

    // ==================== URL TESTS ====================

    public function testUrl(): void
    {
        $request = Request::create('https://example.com/users?page=1', 'GET');

        $url = $request->url();

        $this->assertStringContainsString('example.com/users', $url);
    }

    public function testFullUrl(): void
    {
        $request = Request::create('https://example.com/users?page=1&sort=asc', 'GET');

        $fullUrl = $request->fullUrl();

        $this->assertStringContainsString('example.com/users', $fullUrl);
        $this->assertStringContainsString('page=1', $fullUrl);
        $this->assertStringContainsString('sort=asc', $fullUrl);
    }

    public function testPath(): void
    {
        $request = Request::create('/api/v1/users', 'GET');

        $this->assertEquals('/api/v1/users', $request->path());
    }

    // ==================== SECURITY TESTS ====================

    public function testIsSecure(): void
    {
        $request = Request::create('https://example.com/test', 'GET');

        $this->assertTrue($request->isSecure());
    }

    public function testIsNotSecure(): void
    {
        $request = Request::create('http://example.com/test', 'GET');

        $this->assertFalse($request->isSecure());
    }

    // ==================== ROUTE PARAMETERS TESTS ====================

    public function testRouteParameters(): void
    {
        $request = Request::create('/users/123', 'GET');

        // Simulate route parameter binding
        $request = $request->withAttribute('route_params', ['id' => '123']);

        $this->assertEquals('123', $request->getAttribute('route_params')['id']);
    }

    // ==================== INPUT VALIDATION TESTS ====================

    public function testHasInput(): void
    {
        $request = Request::create('/test', 'POST', [
            'name' => 'John',
            'email' => 'john@example.com'
        ]);

        $this->assertTrue($request->has('name'));
        $this->assertTrue($request->has('email'));
        $this->assertFalse($request->has('password'));
    }

    public function testInputWithMultipleKeys(): void
    {
        $request = Request::create('/test', 'POST', [
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => 25
        ]);

        $data = $request->only(['name', 'email']);

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayNotHasKey('age', $data);
    }

    public function testInputExcept(): void
    {
        $request = Request::create('/test', 'POST', [
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret'
        ]);

        $data = $request->except(['password']);

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayNotHasKey('password', $data);
    }

    // ==================== USER AGENT TESTS ====================

    public function testUserAgent(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; TestBot/1.0)'
        ]);

        $this->assertEquals('Mozilla/5.0 (compatible; TestBot/1.0)', $request->userAgent());
    }

    // ==================== REAL-WORLD SCENARIOS ====================

    public function testApiRequestWithAuthentication(): void
    {
        $json = json_encode(['title' => 'New Post', 'content' => 'Post content']);

        $request = Request::create('/api/posts', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer api-token-123',
            'HTTP_ACCEPT' => 'application/json',
        ], $json);

        $this->assertTrue($request->isJson());
        $this->assertTrue($request->expectsJson());
        $this->assertEquals('api-token-123', $request->bearerToken());
        $this->assertEquals('New Post', $request->input('title'));
        $this->assertEquals('Post content', $request->input('content'));
    }

    public function testFormRequestWithFiles(): void
    {
        $request = Request::create('/upload', 'POST', [
            'title' => 'Image Upload',
            'description' => 'Test upload'
        ]);

        $this->assertEquals('Image Upload', $request->input('title'));
        $this->assertEquals('Test upload', $request->input('description'));
    }

    public function testPaginationRequest(): void
    {
        $request = Request::create('/api/users?page=2&per_page=20&sort=name&order=asc', 'GET');

        $this->assertEquals('2', $request->query('page'));
        $this->assertEquals('20', $request->query('per_page'));
        $this->assertEquals('name', $request->query('sort'));
        $this->assertEquals('asc', $request->query('order'));
    }

    public function testSearchRequest(): void
    {
        $request = Request::create('/search?q=laravel+php&category=tutorials&tags[]=php&tags[]=web', 'GET');

        $this->assertEquals('laravel+php', $request->query('q'));
        $this->assertEquals('tutorials', $request->query('category'));
        $this->assertIsArray($request->query('tags'));
    }
}
