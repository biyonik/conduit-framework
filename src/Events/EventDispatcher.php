<?php

declare(strict_types=1);

namespace Conduit\Events;

use Conduit\Events\Contracts\EventDispatcherInterface;
use Conduit\Core\Contracts\ContainerInterface;

/**
 * EventDispatcher
 * 
 * Framework event dispatcher implementation.
 * Manages event listeners and dispatches events throughout the application.
 * 
 * Features:
 * - Event listener registration
 * - Wildcard event matching (user.*, eloquent.*)
 * - Priority-based listener execution
 * - Event subscriber support
 * - Transaction-aware events
 * - Queued event flushing
 * 
 * @package Conduit\Events
 */
class EventDispatcher implements EventDispatcherInterface
{
    /**
     * Registered event listeners
     * 
     * @var array<string, array<array{listener: callable|string, priority: int}>>
     */
    protected array $listeners = [];

    /**
     * Wildcard listeners
     * 
     * @var array<string, array<array{listener: callable|string, priority: int}>>
     */
    protected array $wildcards = [];

    /**
     * Queued events (for transaction-aware dispatching)
     * 
     * @var array<array{event: string|object, payload: mixed}>
     */
    protected array $queuedEvents = [];

    /**
     * Container instance for resolving listeners
     */
    protected ?ContainerInterface $container = null;

    /**
     * Constructor
     * 
     * @param ContainerInterface|null $container
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function listen(string $event, callable|string $listener, int $priority = 0): void
    {
        // Check if this is a wildcard event
        if (str_contains($event, '*')) {
            $this->wildcards[$event][] = [
                'listener' => $listener,
                'priority' => $priority,
            ];
        } else {
            $this->listeners[$event][] = [
                'listener' => $listener,
                'priority' => $priority,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(string|object $event, mixed $payload = null, bool $halt = false): array
    {
        $eventName = is_object($event) ? get_class($event) : $event;
        $eventPayload = is_object($event) ? $event : $payload;

        $responses = [];
        $listeners = $this->getListeners($eventName);

        foreach ($listeners as $listener) {
            $response = $this->callListener($listener, $eventPayload);
            
            // If halting and response is not null, stop propagation
            if ($halt && $response !== null) {
                return [$response];
            }
            
            $responses[] = $response;
        }

        return $responses;
    }

    /**
     * {@inheritDoc}
     */
    public function subscribe(object|string $subscriber): void
    {
        $subscriberInstance = is_string($subscriber) 
            ? $this->resolveSubscriber($subscriber)
            : $subscriber;

        // Call subscribe method on subscriber
        if (method_exists($subscriberInstance, 'subscribe')) {
            $subscriberInstance->subscribe($this);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function dispatchAfterCommit(string|object $event, mixed $payload = null): void
    {
        $this->queuedEvents[] = [
            'event' => $event,
            'payload' => $payload,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function flushQueuedEvents(): void
    {
        foreach ($this->queuedEvents as $queuedEvent) {
            $this->dispatch($queuedEvent['event'], $queuedEvent['payload']);
        }

        $this->queuedEvents = [];
    }

    /**
     * {@inheritDoc}
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->getListeners($event));
    }

    /**
     * {@inheritDoc}
     */
    public function getListeners(string $event): array
    {
        $listeners = [];

        // Get direct listeners
        if (isset($this->listeners[$event])) {
            $listeners = array_merge($listeners, $this->listeners[$event]);
        }

        // Get wildcard listeners
        foreach ($this->wildcards as $pattern => $wildcardListeners) {
            if ($this->matchesWildcard($event, $pattern)) {
                $listeners = array_merge($listeners, $wildcardListeners);
            }
        }

        // Sort by priority (higher priority first)
        usort($listeners, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        // Extract just the listener callbacks
        return array_map(fn($item) => $item['listener'], $listeners);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * Call a listener with the event payload
     * 
     * @param callable|string $listener Listener callback or class name
     * @param mixed $payload Event payload
     * @return mixed
     */
    protected function callListener(callable|string $listener, mixed $payload): mixed
    {
        if (is_string($listener)) {
            $listener = $this->resolveListener($listener);
        }

        return $listener($payload);
    }

    /**
     * Resolve a listener from container
     * 
     * @param string $listener Listener class name
     * @return callable
     */
    protected function resolveListener(string $listener): callable
    {
        if ($this->container !== null) {
            // Check if it's a class@method format
            if (str_contains($listener, '@')) {
                [$class, $method] = explode('@', $listener, 2);
                $instance = $this->container->make($class);
                return [$instance, $method];
            }

            // Otherwise, assume it's a class with __invoke or handle method
            $instance = $this->container->make($listener);
            
            if (method_exists($instance, 'handle')) {
                return [$instance, 'handle'];
            }
            
            return $instance;
        }

        // Fallback: create instance manually
        if (str_contains($listener, '@')) {
            [$class, $method] = explode('@', $listener, 2);
            $instance = new $class();
            return [$instance, $method];
        }

        $instance = new $listener();
        
        if (method_exists($instance, 'handle')) {
            return [$instance, 'handle'];
        }
        
        return $instance;
    }

    /**
     * Resolve a subscriber from container
     * 
     * @param string $subscriber Subscriber class name
     * @return object
     */
    protected function resolveSubscriber(string $subscriber): object
    {
        if ($this->container !== null) {
            return $this->container->make($subscriber);
        }

        return new $subscriber();
    }

    /**
     * Check if event name matches wildcard pattern
     * 
     * @param string $event Event name
     * @param string $pattern Wildcard pattern
     * @return bool
     */
    protected function matchesWildcard(string $event, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = str_replace(
            ['*', '.'],
            ['.*', '\.'],
            $pattern
        );

        return (bool) preg_match('/^' . $regex . '$/', $event);
    }
}
