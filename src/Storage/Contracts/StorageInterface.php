<?php

declare(strict_types=1);

namespace Conduit\Storage\Contracts;

interface StorageInterface
{
    public function put(string $path, string $contents): bool;
    public function get(string $path): string|false;
    public function exists(string $path): bool;
    public function delete(string $path): bool;
    public function copy(string $from, string $to): bool;
    public function move(string $from, string $to): bool;
    public function size(string $path): int|false;
    public function lastModified(string $path): int|false;
    public function files(string $directory = ''): array;
    public function directories(string $directory = ''): array;
    public function makeDirectory(string $path): bool;
    public function deleteDirectory(string $directory): bool;
}
