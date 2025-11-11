<?php

declare(strict_types=1);

namespace Conduit\Http\Message;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use InvalidArgumentException;

/**
 * PSR-7 Stream Implementation
 * 
 * PHP stream resource'larını wrap eder.
 * Request/Response body için kullanılır.
 * Memory efficient: Large files chunk-chunk işlenir.
 * 
 * @package Conduit\Http\Message
 */
class Stream implements StreamInterface
{
    /**
     * PHP stream resource
     * 
     * @var resource|null
     */
    private $stream;

    /**
     * Stream metadata
     */
    private ?array $metadata = null;

    /**
     * Readable stream mı?
     */
    private ?bool $readable = null;

    /**
     * Writable stream mı?
     */
    private ?bool $writable = null;

    /**
     * Seekable stream mı?
     */
    private ?bool $seekable = null;

    /**
     * Stream size (byte)
     */
    private ?int $size = null;

    /**
     * Readable stream mode'ları
     */
    private const READABLE_MODES = [
        'r', 'r+', 'w+', 'a+', 'x+', 'c+',
        'rb', 'r+b', 'w+b', 'a+b', 'x+b', 'c+b',
        'rt', 'r+t', 'w+t', 'a+t', 'x+t', 'c+t',
    ];

    /**
     * Writable stream mode'ları
     */
    private const WRITABLE_MODES = [
        'r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+',
        'r+b', 'wb', 'w+b', 'ab', 'a+b', 'xb', 'x+b', 'cb', 'c+b',
        'r+t', 'wt', 'w+t', 'at', 'a+t', 'xt', 'x+t', 'ct', 'c+t',
    ];

    /**
     * Constructor
     * 
     * @param resource|string $stream PHP stream resource veya string content
     * @param string $mode Stream mode (default: 'r')
     * @throws InvalidArgumentException
     */
    public function __construct($stream, string $mode = 'r')
    {
        // Eğer string ise, temporary stream oluştur
        if (is_string($stream)) {
            $resource = fopen('php://temp', 'r+');
            if ($resource === false) {
                throw new RuntimeException('Unable to create temporary stream');
            }
            if ($stream !== '') {
                fwrite($resource, $stream);
                rewind($resource);
            }
            $stream = $resource;
        }

        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream must be a resource or string');
        }

        $this->stream = $stream;
        $this->metadata = stream_get_meta_data($this->stream);
    }

    /**
     * Factory method: String'den stream oluştur
     * 
     * @param string $content İçerik
     * @return self
     */
    public static function create(string $content = ''): self
    {
        return new self($content);
    }

    /**
     * Factory method: File'dan stream oluştur
     * 
     * @param string $filename Dosya yolu
     * @param string $mode Açılış modu (default: 'r')
     * @return self
     * @throws RuntimeException
     */
    public static function createFromFile(string $filename, string $mode = 'r'): self
    {
        $resource = @fopen($filename, $mode);
        
        if ($resource === false) {
            throw new RuntimeException("Unable to open file: {$filename}");
        }

        return new self($resource);
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }
            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        if ($this->stream !== null) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->detach();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function detach()
    {
        if ($this->stream === null) {
            return null;
        }

        $result = $this->stream;
        $this->stream = null;
        $this->metadata = null;
        $this->readable = null;
        $this->writable = null;
        $this->seekable = null;
        $this->size = null;

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): ?int
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if ($this->stream === null) {
            return null;
        }

        // fstat ile file size al
        $stats = fstat($this->stream);
        if (isset($stats['size'])) {
            $this->size = $stats['size'];
            return $this->size;
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function tell(): int
    {
        if ($this->stream === null) {
            throw new RuntimeException('Stream is detached');
        }

        $position = ftell($this->stream);

        if ($position === false) {
            throw new RuntimeException('Unable to determine stream position');
        }

        return $position;
    }

    /**
     * {@inheritDoc}
     */
    public function eof(): bool
    {
        if ($this->stream === null) {
            return true;
        }

        return feof($this->stream);
    }

    /**
     * {@inheritDoc}
     */
    public function isSeekable(): bool
    {
        if ($this->seekable !== null) {
            return $this->seekable;
        }

        if ($this->stream === null) {
            return false;
        }

        $this->seekable = $this->metadata['seekable'] ?? false;

        return $this->seekable;
    }

    /**
     * {@inheritDoc}
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable');
        }

        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException('Unable to seek to stream position');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * {@inheritDoc}
     */
    public function isWritable(): bool
    {
        if ($this->writable !== null) {
            return $this->writable;
        }

        if ($this->stream === null) {
            return false;
        }

        $mode = $this->metadata['mode'] ?? '';
        $this->writable = false;

        foreach (self::WRITABLE_MODES as $writableMode) {
            if (str_contains($mode, $writableMode)) {
                $this->writable = true;
                break;
            }
        }

        return $this->writable;
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable');
        }

        // Size cache invalidate
        $this->size = null;

        $result = fwrite($this->stream, $string);

        if ($result === false) {
            throw new RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable(): bool
    {
        if ($this->readable !== null) {
            return $this->readable;
        }

        if ($this->stream === null) {
            return false;
        }

        $mode = $this->metadata['mode'] ?? '';
        $this->readable = false;

        foreach (self::READABLE_MODES as $readableMode) {
            if (str_contains($mode, $readableMode)) {
                $this->readable = true;
                break;
            }
        }

        return $this->readable;
    }

    /**
     * {@inheritDoc}
     */
    public function read(int $length): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable');
        }

        $result = fread($this->stream, $length);

        if ($result === false) {
            throw new RuntimeException('Unable to read from stream');
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable');
        }

        $result = stream_get_contents($this->stream);

        if ($result === false) {
            throw new RuntimeException('Unable to read stream contents');
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(?string $key = null)
    {
        if ($this->stream === null) {
            return $key === null ? [] : null;
        }

        if ($this->metadata === null) {
            $this->metadata = stream_get_meta_data($this->stream);
        }

        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * Destructor: Stream'i kapat
     */
    public function __destruct()
    {
        $this->close();
    }
}