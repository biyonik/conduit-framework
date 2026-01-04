<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Conduit\Database\QueryBuilder;
use Conduit\Database\Connection;
use Conduit\Database\Grammar\MySQLGrammar;
use Conduit\Database\Collection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class QueryBuilderTest extends TestCase
{
    protected QueryBuilder $builder;
    protected Connection|MockObject $connection;
    protected MySQLGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->grammar = new MySQLGrammar();
        $this->builder = new QueryBuilder($this->connection, $this->grammar);
    }

    // ==================== SELECT TESTS ====================

    public function testBasicSelect(): void
    {
        $this->builder->from('users')->select('id', 'name');

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT `id`, `name` FROM `users`', $sql);
    }

    public function testSelectAll(): void
    {
        $this->builder->from('users');

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `users`', $sql);
    }

    public function testSelectWithArray(): void
    {
        $this->builder->from('users')->select(['id', 'name', 'email']);

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT `id`, `name`, `email` FROM `users`', $sql);
    }

    // ==================== WHERE TESTS ====================

    public function testBasicWhere(): void
    {
        $this->builder->from('users')->where('id', '=', 1);

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertEquals('SELECT * FROM `users` WHERE `id` = ?', $sql);
        $this->assertEquals([1], $bindings);
    }

    public function testWhereShorthand(): void
    {
        $this->builder->from('users')->where('id', 1);

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertEquals('SELECT * FROM `users` WHERE `id` = ?', $sql);
        $this->assertEquals([1], $bindings);
    }

    public function testMultipleWhere(): void
    {
        $this->builder->from('users')
            ->where('status', 'active')
            ->where('age', '>', 18);

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertEquals('SELECT * FROM `users` WHERE `status` = ? AND `age` > ?', $sql);
        $this->assertEquals(['active', 18], $bindings);
    }

    public function testOrWhere(): void
    {
        $this->builder->from('users')
            ->where('status', 'active')
            ->orWhere('role', 'admin');

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `users` WHERE `status` = ? OR `role` = ?', $sql);
    }

    public function testWhereIn(): void
    {
        $this->builder->from('users')->whereIn('id', [1, 2, 3]);

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertEquals('SELECT * FROM `users` WHERE `id` IN (?, ?, ?)', $sql);
        $this->assertEquals([1, 2, 3], $bindings);
    }

    public function testWhereNotIn(): void
    {
        $this->builder->from('users')->whereNotIn('status', ['banned', 'suspended']);

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `users` WHERE `status` NOT IN (?, ?)', $sql);
    }

    public function testWhereNull(): void
    {
        $this->builder->from('users')->whereNull('deleted_at');

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `users` WHERE `deleted_at` IS NULL', $sql);
    }

    public function testWhereNotNull(): void
    {
        $this->builder->from('users')->whereNotNull('email_verified_at');

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `users` WHERE `email_verified_at` IS NOT NULL', $sql);
    }

    public function testWhereBetween(): void
    {
        $this->builder->from('users')->whereBetween('age', 18, 65);

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertEquals('SELECT * FROM `users` WHERE `age` BETWEEN ? AND ?', $sql);
        $this->assertEquals([18, 65], $bindings);
    }

    // ==================== JOIN TESTS ====================

    public function testInnerJoin(): void
    {
        $this->builder->from('users')
            ->join('posts', 'users.id', '=', 'posts.user_id');

        $sql = $this->builder->toSql();

        $this->assertEquals(
            'SELECT * FROM `users` INNER JOIN `posts` ON `users`.`id` = `posts`.`user_id`',
            $sql
        );
    }

    public function testLeftJoin(): void
    {
        $this->builder->from('users')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id');

        $sql = $this->builder->toSql();

        $this->assertStringContainsString('LEFT JOIN', $sql);
    }

    public function testRightJoin(): void
    {
        $this->builder->from('users')
            ->rightJoin('posts', 'users.id', '=', 'posts.user_id');

        $sql = $this->builder->toSql();

        $this->assertStringContainsString('RIGHT JOIN', $sql);
    }

    public function testMultipleJoins(): void
    {
        $this->builder->from('users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->join('comments', 'posts.id', '=', 'comments.post_id');

        $sql = $this->builder->toSql();

        $this->assertStringContainsString('INNER JOIN `posts`', $sql);
        $this->assertStringContainsString('INNER JOIN `comments`', $sql);
    }

    // ==================== ORDER BY TESTS ====================

    public function testOrderBy(): void
    {
        $this->builder->from('users')->orderBy('created_at', 'DESC');

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `users` ORDER BY `created_at` DESC', $sql);
    }

    public function testOrderByDefaultAscending(): void
    {
        $this->builder->from('users')->orderBy('name');

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `users` ORDER BY `name` ASC', $sql);
    }

    public function testMultipleOrderBy(): void
    {
        $this->builder->from('users')
            ->orderBy('status', 'ASC')
            ->orderBy('created_at', 'DESC');

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `users` ORDER BY `status` ASC, `created_at` DESC', $sql);
    }

    // ==================== GROUP BY TESTS ====================

    public function testGroupBy(): void
    {
        $this->builder->from('orders')->groupBy('customer_id');

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `orders` GROUP BY `customer_id`', $sql);
    }

    public function testMultipleGroupBy(): void
    {
        $this->builder->from('orders')->groupBy('customer_id', 'status');

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `orders` GROUP BY `customer_id`, `status`', $sql);
    }

    public function testHaving(): void
    {
        $this->builder->from('orders')
            ->groupBy('customer_id')
            ->having('total', '>', 1000);

        $sql = $this->builder->toSql();

        $this->assertStringContainsString('GROUP BY `customer_id`', $sql);
        $this->assertStringContainsString('HAVING `total` > ?', $sql);
    }

    // ==================== LIMIT & OFFSET TESTS ====================

    public function testLimit(): void
    {
        $this->builder->from('users')->limit(10);

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `users` LIMIT 10', $sql);
    }

    public function testOffset(): void
    {
        $this->builder->from('users')->limit(10)->offset(20);

        $sql = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM `users` LIMIT 10 OFFSET 20', $sql);
    }

    // ==================== AGGREGATE TESTS ====================

    public function testCount(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->willReturn([['aggregate' => 10]]);

        $count = $this->builder->from('users')->count();

        $this->assertEquals(10, $count);
    }

    public function testMax(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->willReturn([['aggregate' => 100]]);

        $max = $this->builder->from('users')->max('age');

        $this->assertEquals(100, $max);
    }

    public function testMin(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->willReturn([['aggregate' => 18]]);

        $min = $this->builder->from('users')->min('age');

        $this->assertEquals(18, $min);
    }

    public function testAvg(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->willReturn([['aggregate' => 35.5]]);

        $avg = $this->builder->from('users')->avg('age');

        $this->assertEquals(35.5, $avg);
    }

    public function testSum(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->willReturn([['aggregate' => 5000]]);

        $sum = $this->builder->from('orders')->sum('total');

        $this->assertEquals(5000, $sum);
    }

    // ==================== INSERT TESTS ====================

    public function testInsert(): void
    {
        $this->connection->expects($this->once())
            ->method('insert')
            ->with(
                $this->equalTo('INSERT INTO `users` (`name`, `email`) VALUES (?, ?)'),
                $this->equalTo(['John Doe', 'john@example.com'])
            )
            ->willReturn(1);

        $id = $this->builder->from('users')->insert([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $this->assertEquals(1, $id);
    }

    public function testBulkInsert(): void
    {
        $this->connection->expects($this->once())
            ->method('insert')
            ->willReturn(1);

        $this->builder->from('users')->insert([
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

        $this->assertTrue(true); // If no exception, test passes
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdate(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                $this->stringContains('UPDATE `users` SET `name` = ?'),
                $this->callback(function ($bindings) {
                    return $bindings[0] === 'Jane Doe' && $bindings[1] === 1;
                })
            )
            ->willReturn(1);

        $affected = $this->builder->from('users')
            ->where('id', 1)
            ->update(['name' => 'Jane Doe']);

        $this->assertEquals(1, $affected);
    }

    public function testIncrement(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                $this->stringContains('UPDATE `users` SET `login_count` = `login_count` + ?'),
                $this->equalTo([1])
            )
            ->willReturn(1);

        $affected = $this->builder->from('users')->increment('login_count');

        $this->assertEquals(1, $affected);
    }

    public function testIncrementWithAmount(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                $this->stringContains('UPDATE `users` SET `points` = `points` + ?'),
                $this->equalTo([10])
            )
            ->willReturn(1);

        $affected = $this->builder->from('users')->increment('points', 10);

        $this->assertEquals(1, $affected);
    }

    public function testDecrement(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                $this->stringContains('UPDATE `users` SET `balance` = `balance` + ?'),
                $this->equalTo([-50])
            )
            ->willReturn(1);

        $affected = $this->builder->from('users')->decrement('balance', 50);

        $this->assertEquals(1, $affected);
    }

    // ==================== DELETE TESTS ====================

    public function testDelete(): void
    {
        $this->connection->expects($this->once())
            ->method('delete')
            ->with(
                $this->equalTo('DELETE FROM `users` WHERE `id` = ?'),
                $this->equalTo([1])
            )
            ->willReturn(1);

        $affected = $this->builder->from('users')->where('id', 1)->delete();

        $this->assertEquals(1, $affected);
    }

    public function testTruncate(): void
    {
        $this->connection->expects($this->once())
            ->method('statement')
            ->with($this->equalTo('TRUNCATE TABLE `users`'))
            ->willReturn(true);

        $result = $this->builder->from('users')->truncate();

        $this->assertTrue($result);
    }

    // ==================== PAGINATION TESTS ====================

    public function testPaginate(): void
    {
        // Mock count query
        $this->connection->expects($this->exactly(2))
            ->method('select')
            ->willReturnOnConsecutiveCalls(
                [['aggregate' => 100]], // count
                [] // data
            );

        $result = $this->builder->from('users')->paginate(15, 1);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertEquals(100, $result['pagination']['total']);
        $this->assertEquals(15, $result['pagination']['per_page']);
        $this->assertEquals(1, $result['pagination']['current_page']);
        $this->assertEquals(7, $result['pagination']['last_page']); // ceil(100/15)
    }

    // ==================== UTILITY TESTS ====================

    public function testExists(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->willReturn([['aggregate' => 1]]);

        $exists = $this->builder->from('users')->where('id', 1)->exists();

        $this->assertTrue($exists);
    }

    public function testDoesntExist(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->willReturn([['aggregate' => 0]]);

        $doesntExist = $this->builder->from('users')->where('id', 999)->doesntExist();

        $this->assertTrue($doesntExist);
    }

    public function testFind(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->willReturn([['id' => 1, 'name' => 'John']]);

        $user = $this->builder->from('users')->find(1);

        $this->assertEquals(['id' => 1, 'name' => 'John'], $user);
    }

    public function testReset(): void
    {
        $this->builder->from('users')
            ->where('id', 1)
            ->orderBy('created_at')
            ->limit(10);

        $this->builder->reset();

        $sql = $this->builder->toSql();

        // After reset, should only have SELECT *
        $this->assertStringNotContainsString('WHERE', $sql);
        $this->assertStringNotContainsString('ORDER BY', $sql);
        $this->assertStringNotContainsString('LIMIT', $sql);
    }

    // ==================== COMPLEX QUERY TESTS ====================

    public function testComplexQueryWithAllClauses(): void
    {
        $this->builder->from('users')
            ->select('users.id', 'users.name', 'COUNT(posts.id) as post_count')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.status', 'active')
            ->where('users.age', '>=', 18)
            ->groupBy('users.id', 'users.name')
            ->having('post_count', '>', 5)
            ->orderBy('post_count', 'DESC')
            ->limit(20)
            ->offset(40);

        $sql = $this->builder->toSql();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('FROM `users`', $sql);
        $this->assertStringContainsString('INNER JOIN `posts`', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('HAVING', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT', $sql);
        $this->assertStringContainsString('OFFSET', $sql);
    }

    public function testBindingsOrder(): void
    {
        $this->builder->from('users')
            ->where('age', '>', 18)
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'moderator'])
            ->whereBetween('created_at', '2024-01-01', '2024-12-31');

        $bindings = $this->builder->getBindings();

        $this->assertEquals([
            18,                    // age > 18
            'active',              // status = active
            'admin', 'moderator',  // role IN
            '2024-01-01', '2024-12-31'  // created_at BETWEEN
        ], $bindings);
    }

    // ==================== SQL INJECTION PROTECTION TESTS ====================

    public function testColumnNameValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid column or table name');

        // Try to inject SQL through column name
        $this->builder->from('users')->select('id; DROP TABLE users; --');

        $this->builder->toSql();
    }

    public function testTableNameValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid column or table name');

        // Try to inject SQL through table name
        $this->builder->from('users; DROP TABLE users; --');

        $this->builder->toSql();
    }
}
