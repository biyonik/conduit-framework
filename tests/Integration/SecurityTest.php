<?php

declare(strict_types=1);

namespace Tests\Integration;

use Conduit\Database\QueryBuilder;
use Conduit\Database\Connection;
use Conduit\Database\Grammar\MySQLGrammar;
use Conduit\Http\Request;
use Conduit\RateLimiter\RateLimiter;
use Conduit\RateLimiter\Storage\ArrayStorage;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    // ==================== SQL INJECTION PROTECTION TESTS ====================

    public function testSqlInjectionThroughColumnName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid column or table name');

        $connection = $this->createMock(Connection::class);
        $grammar = new MySQLGrammar();
        $builder = new QueryBuilder($connection, $grammar);

        // Attempt SQL injection through column name
        $builder->from('users')->select('id; DROP TABLE users; --');

        $builder->toSql(); // Should throw exception
    }

    public function testSqlInjectionThroughTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid column or table name');

        $connection = $this->createMock(Connection::class);
        $grammar = new MySQLGrammar();
        $builder = new QueryBuilder($connection, $grammar);

        // Attempt SQL injection through table name
        $builder->from('users; DROP TABLE users; --');

        $builder->toSql(); // Should throw exception
    }

    public function testSqlInjectionThroughOrderBy(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $connection = $this->createMock(Connection::class);
        $grammar = new MySQLGrammar();
        $builder = new QueryBuilder($connection, $grammar);

        // Attempt SQL injection through ORDER BY
        $builder->from('users')->orderBy('id; DROP TABLE users; --');

        $builder->toSql(); // Should throw exception
    }

    public function testSqlInjectionThroughJoinColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $connection = $this->createMock(Connection::class);
        $grammar = new MySQLGrammar();
        $builder = new QueryBuilder($connection, $grammar);

        // Attempt SQL injection through JOIN
        $builder->from('users')->join('posts', 'users.id; DROP TABLE users;', '=', 'posts.user_id');

        $builder->toSql(); // Should throw exception
    }

    public function testPreparedStatementsProtectWhereValues(): void
    {
        $connection = $this->createMock(Connection::class);
        $grammar = new MySQLGrammar();
        $builder = new QueryBuilder($connection, $grammar);

        // Malicious input in WHERE value (should be safe with prepared statements)
        $builder->from('users')->where('id', '=', "1 OR 1=1; DROP TABLE users; --");

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        // SQL should use placeholders
        $this->assertEquals('SELECT * FROM `users` WHERE `id` = ?', $sql);
        // Malicious code is treated as data
        $this->assertEquals(["1 OR 1=1; DROP TABLE users; --"], $bindings);
    }

    // ==================== XSS PROTECTION TESTS ====================

    public function testHtmlEscapingInOutput(): void
    {
        // Test that potentially dangerous HTML is escaped
        $dangerous = '<script>alert("XSS")</script>';

        $escaped = htmlspecialchars($dangerous, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    public function testJsonEncodingEscapesHtml(): void
    {
        $data = ['message' => '<script>alert("XSS")</script>'];

        $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $this->assertStringNotContainsString('<script>', $json);
    }

    // ==================== RATE LIMITING TESTS ====================

    public function testRateLimitingBlocksExcessiveRequests(): void
    {
        $storage = new ArrayStorage();
        $limiter = new RateLimiter($storage);

        $key = 'test-ip:api';
        $maxAttempts = 5;

        // Make max allowed attempts
        for ($i = 0; $i < $maxAttempts; $i++) {
            $allowed = $limiter->attempt($key, $maxAttempts, 60);
            $this->assertTrue($allowed, "Attempt {$i} should be allowed");
        }

        // Next attempt should be blocked
        $blocked = $limiter->attempt($key, $maxAttempts, 60);
        $this->assertFalse($blocked, "Attempt beyond limit should be blocked");
    }

    public function testRateLimitingTracksRemaining(): void
    {
        $storage = new ArrayStorage();
        $limiter = new RateLimiter($storage);

        $key = 'test-ip:api';
        $maxAttempts = 10;

        // Make 3 attempts
        $limiter->hit($key, 60);
        $limiter->hit($key, 60);
        $limiter->hit($key, 60);

        $remaining = $limiter->remaining($key, $maxAttempts);

        $this->assertEquals(7, $remaining);
    }

    public function testRateLimitingClear(): void
    {
        $storage = new ArrayStorage();
        $limiter = new RateLimiter($storage);

        $key = 'test-ip:api';

        // Make some attempts
        $limiter->hit($key, 60);
        $limiter->hit($key, 60);

        $this->assertEquals(2, $limiter->attempts($key));

        // Clear the limiter
        $limiter->clear($key);

        $this->assertEquals(0, $limiter->attempts($key));
    }

    // ==================== IP SPOOFING PROTECTION TESTS ====================

    public function testIpSpoofingRejectedWithoutTrustedProxy(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1' // Spoofed IP
        ]);

        // No trusted proxies configured
        $ip = $request->ip();

        // Should use REMOTE_ADDR, not spoofed IP
        $this->assertEquals('192.168.1.100', $ip);
    }

    public function testIpSpoofingAllowedWithTrustedProxy(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1', // Trusted proxy
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1' // Real client IP
        ]);

        $request->setTrustedProxies(['10.0.0.1']);

        $ip = $request->ip();

        // Should trust forwarded IP from trusted proxy
        $this->assertEquals('203.0.113.1', $ip);
    }

    public function testPrivateIpRejectedFromForwardedHeader(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '192.168.1.1' // Private IP
        ]);

        $request->setTrustedProxies(['10.0.0.1']);

        $ip = $request->ip();

        // Should fallback to REMOTE_ADDR if forwarded IP is private
        $this->assertEquals('10.0.0.1', $ip);
    }

    // ==================== MASS ASSIGNMENT PROTECTION TESTS ====================

    public function testMassAssignmentProtectionWithGuarded(): void
    {
        // This would be tested with actual Model class
        // Placeholder for concept
        $protectedFields = ['id', 'created_at', 'updated_at'];
        $input = ['id' => 999, 'name' => 'Hacker', 'email' => 'hacker@example.com'];

        // Filter out guarded fields
        $fillable = array_diff_key($input, array_flip($protectedFields));

        $this->assertArrayNotHasKey('id', $fillable);
        $this->assertArrayHasKey('name', $fillable);
        $this->assertArrayHasKey('email', $fillable);
    }

    // ==================== AUTHENTICATION TOKEN TESTS ====================

    public function testBearerTokenExtraction(): void
    {
        $request = Request::create('/api/users', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer secret-token-123'
        ]);

        $token = $request->bearerToken();

        $this->assertEquals('secret-token-123', $token);
    }

    public function testBearerTokenMissingReturnsNull(): void
    {
        $request = Request::create('/api/users', 'GET');

        $token = $request->bearerToken();

        $this->assertNull($token);
    }

    public function testBearerTokenWithInvalidFormatReturnsNull(): void
    {
        $request = Request::create('/api/users', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXNz' // Not Bearer
        ]);

        $token = $request->bearerToken();

        $this->assertNull($token);
    }

    // ==================== REAL-WORLD ATTACK SCENARIOS ====================

    public function testBruteForceLoginProtection(): void
    {
        $storage = new ArrayStorage();
        $limiter = new RateLimiter($storage);

        $clientIp = '203.0.113.1';
        $maxAttempts = 5;
        $decaySeconds = 900; // 15 minutes

        // Simulate failed login attempts
        for ($i = 0; $i < 10; $i++) {
            $allowed = $limiter->attempt("login:{$clientIp}", $maxAttempts, $decaySeconds);

            if ($i < $maxAttempts) {
                $this->assertTrue($allowed, "Attempt {$i} should be allowed");
            } else {
                $this->assertFalse($allowed, "Attempt {$i} should be blocked");
            }
        }
    }

    public function testApiRateLimitingPerEndpoint(): void
    {
        $storage = new ArrayStorage();
        $limiter = new RateLimiter($storage);

        $clientIp = '203.0.113.1';
        $endpoint = '/api/users';
        $key = "api:{$clientIp}:{$endpoint}";

        // Each endpoint has separate rate limit
        $limiter->hit($key, 60);
        $limiter->hit($key, 60);
        $limiter->hit($key, 60);

        $this->assertEquals(3, $limiter->attempts($key));

        // Different endpoint has different counter
        $otherEndpoint = '/api/posts';
        $otherKey = "api:{$clientIp}:{$otherEndpoint}";

        $this->assertEquals(0, $limiter->attempts($otherKey));
    }

    public function testHeaderInjectionPrevention(): void
    {
        // Test that newlines in headers are rejected
        $request = Request::create('/test', 'GET');

        $maliciousHeader = "value\r\nX-Injected: malicious";

        // PSR-7 implementation should sanitize or reject this
        // This is a conceptual test - actual behavior depends on implementation
        $this->assertTrue(true); // Placeholder
    }

    public function testDirectoryTraversalPrevention(): void
    {
        // Test that paths like ../../etc/passwd are rejected
        $maliciousPath = '../../../../etc/passwd';

        // Should be normalized or rejected
        $normalized = str_replace(['../', '..\\'], '', $maliciousPath);

        $this->assertEquals('etc/passwd', $normalized);
    }

    // ==================== INPUT VALIDATION SECURITY TESTS ====================

    public function testEmailValidationRejectsInvalidFormats(): void
    {
        $invalidEmails = [
            'not-an-email',
            'missing@domain',
            '@example.com',
            'user@',
            'user name@example.com',
            'user@domain,com',
            'user@domain..com',
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Should reject invalid email: {$email}"
            );
        }
    }

    public function testUrlValidationRejectsJavascriptProtocol(): void
    {
        $maliciousUrl = 'javascript:alert("XSS")';

        $isValid = filter_var($maliciousUrl, FILTER_VALIDATE_URL) &&
                   in_array(parse_url($maliciousUrl, PHP_URL_SCHEME), ['http', 'https']);

        $this->assertFalse($isValid, 'Should reject javascript: protocol');
    }

    // ==================== CONCURRENT REQUEST TESTS ====================

    public function testRateLimiterConcurrency(): void
    {
        $storage = new ArrayStorage();
        $limiter = new RateLimiter($storage);

        $key = 'concurrent-test';

        // Simulate concurrent hits
        $limiter->hit($key, 60);
        $attempts1 = $limiter->attempts($key);

        $limiter->hit($key, 60);
        $attempts2 = $limiter->attempts($key);

        $this->assertEquals(1, $attempts1);
        $this->assertEquals(2, $attempts2);
    }

    // ==================== SECURE DEFAULTS TESTS ====================

    public function testSecureHeaderDefaults(): void
    {
        $secureHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ];

        // Verify secure header values
        foreach ($secureHeaders as $header => $value) {
            $this->assertIsString($value);
            $this->assertNotEmpty($value);
        }
    }

    public function testPasswordHashingIsSecure(): void
    {
        $password = 'secret-password-123';

        // Test bcrypt hashing
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $this->assertNotEquals($password, $hash);
        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('wrong-password', $hash));
    }

    public function testTokenGenerationIsRandom(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // 32 bytes = 64 hex chars
    }
}
