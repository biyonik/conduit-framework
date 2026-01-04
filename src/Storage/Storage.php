<?php

declare(strict_types=1);

namespace Conduit\Storage;

use Conduit\Storage\Contracts\StorageInterface;

/**
 * Storage - Local Filesystem
 *
 * Shared hosting friendly file storage.
 *
 * @package Conduit\Storage
 */
class Storage implements StorageInterface
{
    protected string $root;
    protected string $visibility;

    public function __construct(string $root, string $visibility = 'private')
    {
        $this->root = rtrim($root, '/');
        $this->visibility = $visibility;

        if (!is_dir($this->root)) {
            @mkdir($this->root, 0755, true);
        }
    }

    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->path($path);
        $this->ensureDirectoryExists(dirname($fullPath));

        $result = file_put_contents($fullPath, $contents, LOCK_EX) !== false;

        if ($result) {
            @chmod($fullPath, $this->visibility === 'public' ? 0644 : 0600);
        }

        return $result;
    }

    public function get(string $path): string|false
    {
        $fullPath = $this->path($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        return file_get_contents($fullPath);
    }

    public function exists(string $path): bool
    {
        return file_exists($this->path($path));
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->path($path);

        if (file_exists($fullPath)) {
            return @unlink($fullPath);
        }

        return false;
    }

    public function copy(string $from, string $to): bool
    {
        return copy($this->path($from), $this->path($to));
    }

    public function move(string $from, string $to): bool
    {
        return rename($this->path($from), $this->path($to));
    }

    public function size(string $path): int|false
    {
        $fullPath = $this->path($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        return filesize($fullPath);
    }

    public function lastModified(string $path): int|false
    {
        $fullPath = $this->path($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        return filemtime($fullPath);
    }

    public function files(string $directory = ''): array
    {
        $fullPath = $this->path($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = glob($fullPath . '/*');

        return array_filter($files, 'is_file');
    }

    public function directories(string $directory = ''): array
    {
        $fullPath = $this->path($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $dirs = glob($fullPath . '/*', GLOB_ONLYDIR);

        return $dirs ?: [];
    }

    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->path($path);

        if (is_dir($fullPath)) {
            return true;
        }

        return @mkdir($fullPath, 0755, true);
    }

    public function deleteDirectory(string $directory): bool
    {
        $fullPath = $this->path($directory);

        if (!is_dir($fullPath)) {
            return false;
        }

        $files = array_diff(scandir($fullPath), ['.', '..']);

        foreach ($files as $file) {
            $path = $fullPath . '/' . $file;

            is_dir($path) ? $this->deleteDirectory($file) : @unlink($path);
        }

        return @rmdir($fullPath);
    }

    public function url(string $path): string
    {
        // For public storage
        if ($this->visibility === 'public') {
            return '/storage/' . ltrim($path, '/');
        }

        throw new \RuntimeException('Cannot generate URL for private storage');
    }

    protected function path(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }
}
