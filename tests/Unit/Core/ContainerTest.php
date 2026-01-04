<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Conduit\Core\Container;
use Conduit\Core\Exceptions\BindingResolutionException;
use Conduit\Core\Exceptions\ContainerException;
use PHPUnit\Framework\TestCase;
use stdClass;

class ContainerTest extends TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    // ==================== BASIC BINDING TESTS ====================

    public function testBindAndResolveSimpleClass(): void
    {
        $this->container->bind(SimpleClass::class);
        $instance = $this->container->make(SimpleClass::class);

        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testBindClosure(): void
    {
        $this->container->bind('foo', fn() => 'bar');
        $result = $this->container->make('foo');

        $this->assertEquals('bar', $result);
    }

    public function testBindInstance(): void
    {
        $object = new stdClass();
        $object->foo = 'bar';

        $this->container->instance('obj', $object);
        $resolved = $this->container->make('obj');

        $this->assertSame($object, $resolved);
        $this->assertEquals('bar', $resolved->foo);
    }

    // ==================== SINGLETON TESTS ====================

    public function testSingleton(): void
    {
        $this->container->singleton(SimpleClass::class);

        $instance1 = $this->container->make(SimpleClass::class);
        $instance2 = $this->container->make(SimpleClass::class);

        $this->assertSame($instance1, $instance2);
    }

    public function testSingletonClosure(): void
    {
        $this->container->singleton('counter', function () {
            static $count = 0;
            return ++$count;
        });

        $first = $this->container->make('counter');
        $second = $this->container->make('counter');

        $this->assertEquals(1, $first);
        $this->assertEquals(1, $second); // Same instance
    }

    public function testNonSingletonCreatesNewInstances(): void
    {
        $this->container->bind(SimpleClass::class);

        $instance1 = $this->container->make(SimpleClass::class);
        $instance2 = $this->container->make(SimpleClass::class);

        $this->assertNotSame($instance1, $instance2);
    }

    // ==================== DEPENDENCY INJECTION TESTS ====================

    public function testAutomaticDependencyResolution(): void
    {
        $this->container->bind(SimpleDependency::class);
        $this->container->bind(ClassWithDependency::class);

        $instance = $this->container->make(ClassWithDependency::class);

        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(SimpleDependency::class, $instance->dependency);
    }

    public function testNestedDependencyResolution(): void
    {
        $this->container->bind(SimpleDependency::class);
        $this->container->bind(ClassWithDependency::class);
        $this->container->bind(ClassWithNestedDependency::class);

        $instance = $this->container->make(ClassWithNestedDependency::class);

        $this->assertInstanceOf(ClassWithNestedDependency::class, $instance);
        $this->assertInstanceOf(ClassWithDependency::class, $instance->classWithDep);
        $this->assertInstanceOf(SimpleDependency::class, $instance->classWithDep->dependency);
    }

    public function testDependencyResolutionWithParameters(): void
    {
        $this->container->bind(SimpleDependency::class);
        $this->container->bind(ClassWithMixedDependencies::class);

        $instance = $this->container->make(ClassWithMixedDependencies::class, [
            'value' => 42
        ]);

        $this->assertInstanceOf(SimpleDependency::class, $instance->dependency);
        $this->assertEquals(42, $instance->value);
    }

    // ==================== CIRCULAR DEPENDENCY TESTS ====================

    public function testCircularDependencyDetection(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->container->bind(CircularA::class);
        $this->container->bind(CircularB::class);

        $this->container->make(CircularA::class);
    }

    // ==================== ALIAS TESTS ====================

    public function testAlias(): void
    {
        $this->container->bind(SimpleClass::class);
        $this->container->alias('simple', SimpleClass::class);

        $instance1 = $this->container->make('simple');
        $instance2 = $this->container->make(SimpleClass::class);

        $this->assertInstanceOf(SimpleClass::class, $instance1);
        $this->assertInstanceOf(SimpleClass::class, $instance2);
    }

    public function testAliasWithSingleton(): void
    {
        $this->container->singleton(SimpleClass::class);
        $this->container->alias('simple', SimpleClass::class);

        $instance1 = $this->container->make('simple');
        $instance2 = $this->container->make(SimpleClass::class);

        $this->assertSame($instance1, $instance2);
    }

    // ==================== INTERFACE BINDING TESTS ====================

    public function testInterfaceBinding(): void
    {
        $this->container->bind(TestInterface::class, ConcreteImplementation::class);

        $instance = $this->container->make(TestInterface::class);

        $this->assertInstanceOf(ConcreteImplementation::class, $instance);
        $this->assertInstanceOf(TestInterface::class, $instance);
    }

    public function testInterfaceBindingWithClosure(): void
    {
        $this->container->bind(TestInterface::class, function () {
            return new ConcreteImplementation();
        });

        $instance = $this->container->make(TestInterface::class);

        $this->assertInstanceOf(ConcreteImplementation::class, $instance);
    }

    // ==================== HAS/BOUND TESTS ====================

    public function testHas(): void
    {
        $this->assertFalse($this->container->has('foo'));

        $this->container->bind('foo', fn() => 'bar');

        $this->assertTrue($this->container->has('foo'));
    }

    public function testBound(): void
    {
        $this->assertFalse($this->container->bound('foo'));

        $this->container->bind('foo', fn() => 'bar');

        $this->assertTrue($this->container->bound('foo'));
    }

    // ==================== ARRAY ACCESS TESTS ====================

    public function testArrayAccessSet(): void
    {
        $this->container['foo'] = fn() => 'bar';

        $this->assertEquals('bar', $this->container->make('foo'));
    }

    public function testArrayAccessGet(): void
    {
        $this->container->bind('foo', fn() => 'bar');

        $this->assertEquals('bar', $this->container['foo']);
    }

    public function testArrayAccessExists(): void
    {
        $this->assertFalse(isset($this->container['foo']));

        $this->container['foo'] = fn() => 'bar';

        $this->assertTrue(isset($this->container['foo']));
    }

    public function testArrayAccessUnset(): void
    {
        $this->container['foo'] = fn() => 'bar';
        $this->assertTrue(isset($this->container['foo']));

        unset($this->container['foo']);

        $this->assertFalse(isset($this->container['foo']));
    }

    // ==================== ERROR HANDLING TESTS ====================

    public function testUnresolvableClassThrowsException(): void
    {
        $this->expectException(BindingResolutionException::class);

        $this->container->make('NonExistentClass');
    }

    public function testUnresolvableDependencyThrowsException(): void
    {
        $this->expectException(BindingResolutionException::class);

        $this->container->make(ClassWithUnresolvableDependency::class);
    }

    // ==================== CONTEXTUAL BINDING TESTS ====================

    public function testMakeWithParameters(): void
    {
        $instance = $this->container->make(ClassWithParameter::class, [
            'value' => 'test-value'
        ]);

        $this->assertEquals('test-value', $instance->value);
    }

    // ==================== REFLECTION TESTS ====================

    public function testResolveClassWithDefaultParameters(): void
    {
        $instance = $this->container->make(ClassWithDefaultParameter::class);

        $this->assertEquals('default', $instance->value);
    }

    public function testResolveClassWithDefaultParametersOverride(): void
    {
        $instance = $this->container->make(ClassWithDefaultParameter::class, [
            'value' => 'custom'
        ]);

        $this->assertEquals('custom', $instance->value);
    }

    // ==================== CALLABLE RESOLUTION TESTS ====================

    public function testCall(): void
    {
        $this->container->bind(SimpleDependency::class);

        $result = $this->container->call(function (SimpleDependency $dep) {
            return $dep instanceof SimpleDependency;
        });

        $this->assertTrue($result);
    }

    public function testCallWithParameters(): void
    {
        $this->container->bind(SimpleDependency::class);

        $result = $this->container->call(
            function (SimpleDependency $dep, string $message) {
                return $message . '-' . get_class($dep);
            },
            ['message' => 'test']
        );

        $this->assertStringContainsString('test', $result);
        $this->assertStringContainsString('SimpleDependency', $result);
    }
}

// ==================== TEST FIXTURES ====================

class SimpleClass
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid();
    }
}

class SimpleDependency
{
    public function getValue(): string
    {
        return 'dependency-value';
    }
}

class ClassWithDependency
{
    public function __construct(public SimpleDependency $dependency)
    {
    }
}

class ClassWithNestedDependency
{
    public function __construct(public ClassWithDependency $classWithDep)
    {
    }
}

class ClassWithMixedDependencies
{
    public function __construct(
        public SimpleDependency $dependency,
        public int $value
    ) {
    }
}

class CircularA
{
    public function __construct(public CircularB $b)
    {
    }
}

class CircularB
{
    public function __construct(public CircularA $a)
    {
    }
}

interface TestInterface
{
    public function test(): string;
}

class ConcreteImplementation implements TestInterface
{
    public function test(): string
    {
        return 'implemented';
    }
}

class ClassWithUnresolvableDependency
{
    public function __construct(UnresolvableClass $dep)
    {
    }
}

class ClassWithParameter
{
    public function __construct(public string $value)
    {
    }
}

class ClassWithDefaultParameter
{
    public function __construct(public string $value = 'default')
    {
    }
}
