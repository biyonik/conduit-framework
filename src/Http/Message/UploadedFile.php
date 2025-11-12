<?php

declare(strict_types=1);

namespace Conduit\Http\Message;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use InvalidArgumentException;

/**
 * PSR-7 UploadedFile Implementation
 * 
 * $_FILES array'ini wrap eder.
 * File upload işlemlerini yönetir:
 * - Validation (size, type, error)
 * - Moving uploaded file
 * - Stream access
 * 
 * @package Conduit\Http\Message
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * File stream
     */
    private ?StreamInterface $stream = null;

    /**
     * Temporary file path (upload edilen dosyanın geçici yolu)
     */
    private ?string $file = null;

    /**
     * File size (bytes)
     */
    private ?int $size;

    /**
     * Upload error code (UPLOAD_ERR_* constants)
     */
    private int $error;

    /**
     * Client filename (kullanıcının verdiği dosya adı)
     */
    private ?string $clientFilename;

    /**
     * Client media type (MIME type)
     */
    private ?string $clientMediaType;

    /**
     * Dosya taşındı mı?
     */
    private bool $moved = false;

    /**
     * PHP upload error mesajları
     */
    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_OK => 'There is no error, the file uploaded with success',
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
    ];

    /**
     * Constructor
     * 
     * @param StreamInterface|string|resource $streamOrFile Stream, file path, veya resource
     * @param int|null $size File size (bytes)
     * @param int $error Upload error code
     * @param string|null $clientFilename Client-provided filename
     * @param string|null $clientMediaType Client-provided media type
     * @throws InvalidArgumentException
     */
    public function __construct(
        $streamOrFile,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ) {
        // Error code validation
        if (!in_array($error, array_keys(self::UPLOAD_ERRORS), true)) {
            throw new InvalidArgumentException('Invalid error code');
        }

        $this->error = $error;
        $this->size = $size;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;

        // Stream veya file path set et
        if ($error === UPLOAD_ERR_OK) {
            if ($streamOrFile instanceof StreamInterface) {
                $this->stream = $streamOrFile;
            } elseif (is_string($streamOrFile)) {
                $this->file = $streamOrFile;
            } elseif (is_resource($streamOrFile)) {
                $this->stream = new Stream($streamOrFile);
            } else {
                throw new InvalidArgumentException('Invalid stream or file provided');
            }
        }
    }

    /**
     * Factory method: $_FILES array'den UploadedFile oluştur
     * 
     * @param array $file $_FILES array element
     * @return self
     */
    public static function createFromFilesArray(array $file): self
    {
        return new self(
            $file['tmp_name'] ?? '',
            $file['size'] ?? null,
            $file['error'] ?? UPLOAD_ERR_NO_FILE,
            $file['name'] ?? null,
            $file['type'] ?? null
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getStream(): StreamInterface
    {
        // Upload error varsa stream dönme
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->getErrorMessage());
        }

        // Zaten taşınmışsa hata
        if ($this->moved) {
            throw new RuntimeException('File has already been moved');
        }

        // Stream zaten varsa dön
        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        // File'dan stream oluştur
        if ($this->file !== null) {
            $this->stream = Stream::createFromFile($this->file, 'r');
            return $this->stream;
        }

        throw new RuntimeException('No stream or file available');
    }

    /**
     * {@inheritDoc}
     */
    public function moveTo(string $targetPath): void
    {
        // Upload error varsa taşıma
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->getErrorMessage());
        }

        // Zaten taşınmışsa hata
        if ($this->moved) {
            throw new RuntimeException('File has already been moved');
        }

        // Target path validation
        if (empty($targetPath)) {
            throw new InvalidArgumentException('Target path cannot be empty');
        }

        // Target directory var mı kontrol et
        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
            throw new RuntimeException("Target directory is not writable: {$targetDirectory}");
        }

        // SAPI (Server API) kontrolü
        // CLI ise move_uploaded_file çalışmaz, rename kullan
        $sapi = PHP_SAPI;
        $isCli = $sapi === 'cli' || $sapi === 'phpdbg';

        if ($this->file !== null) {
            // File-based upload
            if ($isCli) {
                // CLI: rename kullan
                if (rename($this->file, $targetPath) === false) {
                    throw new RuntimeException("Unable to move file to: {$targetPath}");
                }
            } else {
                // Web: move_uploaded_file kullan (güvenlik için)
                if (move_uploaded_file($this->file, $targetPath) === false) {
                    throw new RuntimeException("Unable to move uploaded file to: {$targetPath}");
                }
            }
        } elseif ($this->stream instanceof StreamInterface) {
            // Stream-based upload: manuel copy
            $handle = fopen($targetPath, 'wb');
            if ($handle === false) {
                throw new RuntimeException("Unable to write to: {$targetPath}");
            }

            $stream = $this->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            while (!$stream->eof()) {
                fwrite($handle, $stream->read(4096)); // 4KB chunks
            }

            fclose($handle);
        }

        $this->moved = true;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * {@inheritDoc}
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Upload error mesajını al
     * 
     * @return string
     */
    public function getErrorMessage(): string
    {
        return self::UPLOAD_ERRORS[$this->error] ?? 'Unknown upload error';
    }

    /**
     * Upload başarılı mı?
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    /**
     * {@inheritDoc}
     */
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * {@inheritDoc}
     */
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    /**
     * File extension'ı al
     * 
     * @return string|null
     */
    public function getClientExtension(): ?string
    {
        if ($this->clientFilename === null) {
            return null;
        }

        return pathinfo($this->clientFilename, PATHINFO_EXTENSION);
    }

    /**
     * Dosya taşındı mı?
     * 
     * @return bool
     */
    public function isMoved(): bool
    {
        return $this->moved;
    }
}