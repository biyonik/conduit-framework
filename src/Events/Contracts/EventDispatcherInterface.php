<?php

declare(strict_types=1);

namespace Conduit\Events\Contracts;

/**
 * EventDispatcherInterface
 * 
 * Event dispatcher contract for the framework.
 * Provides event listening and dispatching capabilities.
 * 
 * @package Conduit\Events\Contracts
 */
interface EventDispatcherInterface
{
    /**
     * Register an event listener
     * 
     * @param string $event Event name (supports wildcards like 'user.*')
     * @param callable|string $listener Listener callback or class name
     * @param int $priority Listener priority (higher runs first)
     * @return void
     */
    public function listen(string $event, callable|string $listener, int $priority = 0): void;

    /**
     * Dispatch an event
     * 
     * @param string|object $event Event name or event object
     * @param mixed $payload Event payload
     * @param bool $halt Stop propagation on first non-null response
     * @return array<mixed> Array of listener results
     */
    public function dispatch(string|object $event, mixed $payload = null, bool $halt = false): array;

    /**
     * Register an event subscriber
     * 
     * @param object|string $subscriber Subscriber instance or class name
     * @return void
     */
    public function subscribe(object|string $subscriber): void;

    /**
     * Dispatch event after database transaction commits
     * 
     * @param string|object $event Event name or event object
     * @param mixed $payload Event payload
     * @return void
     */
    public function dispatchAfterCommit(string|object $event, mixed $payload = null): void;

    /**
     * Flush queued events (typically after transaction commit)
     * 
     * @return void
     */
    public function flushQueuedEvents(): void;

    /**
     * Check if event has listeners
     * 
     * @param string $event Event name
     * @return bool
     */
    public function hasListeners(string $event): bool;

    /**
     * Get all listeners for an event
     * 
     * @param string $event Event name
     * @return array<callable>
     */
    public function getListeners(string $event): array;

    /**
     * Remove all listeners for an event
     * 
     * @param string $event Event name
     * @return void
     */
    public function forget(string $event): void;
}
