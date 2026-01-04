<?php

declare(strict_types=1);

namespace Conduit\Authorization\Models;

use Conduit\Database\Model;
use Conduit\Database\Relations\BelongsTo;

/**
 * Field Restriction Model
 *
 * Represents field-level (column-level) permissions.
 * Allows hiding, masking, or making fields read-only based on permissions.
 *
 * Restriction Types:
 * - hidden: Field is completely removed from results
 * - masked: Field value is masked (e.g., ****1234 for credit card)
 * - readonly: Field can be viewed but not modified
 *
 * @property int $id
 * @property int $permission_id
 * @property string $field_name
 * @property string $restriction_type
 * @property string|null $mask_pattern
 * @property int $created_at
 * @property int $updated_at
 *
 * @package Conduit\Authorization\Models
 */
class FieldRestriction extends Model
{
    /**
     * Restriction type constants
     */
    public const TYPE_HIDDEN = 'hidden';
    public const TYPE_MASKED = 'masked';
    public const TYPE_READONLY = 'readonly';

    /**
     * Table name
     *
     * @var string
     */
    protected string $table = 'field_restrictions';

    /**
     * Mass assignment fillable attributes
     *
     * @var array
     */
    protected array $fillable = [
        'permission_id',
        'field_name',
        'restriction_type',
        'mask_pattern',
    ];

    /**
     * Attribute casts
     *
     * @var array
     */
    protected array $casts = [
        'id' => 'int',
        'permission_id' => 'int',
        'created_at' => 'int',
        'updated_at' => 'int',
    ];

    /**
     * Permission relationship
     *
     * @return BelongsTo
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    /**
     * Create a hidden field restriction
     *
     * @param int $permissionId
     * @param string $fieldName
     * @return self
     */
    public static function createHidden(int $permissionId, string $fieldName): self
    {
        return static::create([
            'permission_id' => $permissionId,
            'field_name' => $fieldName,
            'restriction_type' => self::TYPE_HIDDEN,
        ]);
    }

    /**
     * Create a masked field restriction
     *
     * @param int $permissionId
     * @param string $fieldName
     * @param string $maskPattern Pattern for masking (default: '***')
     * @return self
     */
    public static function createMasked(
        int $permissionId,
        string $fieldName,
        string $maskPattern = '***'
    ): self {
        return static::create([
            'permission_id' => $permissionId,
            'field_name' => $fieldName,
            'restriction_type' => self::TYPE_MASKED,
            'mask_pattern' => $maskPattern,
        ]);
    }

    /**
     * Create a readonly field restriction
     *
     * @param int $permissionId
     * @param string $fieldName
     * @return self
     */
    public static function createReadonly(int $permissionId, string $fieldName): self
    {
        return static::create([
            'permission_id' => $permissionId,
            'field_name' => $fieldName,
            'restriction_type' => self::TYPE_READONLY,
        ]);
    }

    /**
     * Check if restriction type is hidden
     *
     * @return bool
     */
    public function isHidden(): bool
    {
        return $this->restriction_type === self::TYPE_HIDDEN;
    }

    /**
     * Check if restriction type is masked
     *
     * @return bool
     */
    public function isMasked(): bool
    {
        return $this->restriction_type === self::TYPE_MASKED;
    }

    /**
     * Check if restriction type is readonly
     *
     * @return bool
     */
    public function isReadonly(): bool
    {
        return $this->restriction_type === self::TYPE_READONLY;
    }

    /**
     * Apply masking to a value
     *
     * @param mixed $value Original value
     * @return mixed Masked value
     */
    public function applyMask(mixed $value): mixed
    {
        if (!$this->isMasked() || $value === null) {
            return $value;
        }

        $pattern = $this->mask_pattern ?? '***';

        // Handle different mask patterns
        if (strpos($pattern, '#') !== false) {
            // Pattern like "XXX-XX-####" - show last N characters
            $showCount = substr_count($pattern, '#');
            $valueStr = (string)$value;
            $hideCount = max(0, strlen($valueStr) - $showCount);

            return str_repeat('*', $hideCount) . substr($valueStr, -$showCount);
        }

        // Simple replacement
        return $pattern;
    }

    /**
     * Get all field restrictions for a permission
     *
     * @param int $permissionId
     * @return array
     */
    public static function getForPermission(int $permissionId): array
    {
        return static::where('permission_id', '=', $permissionId)
            ->get()
            ->toArray();
    }

    /**
     * Get restrictions grouped by type for a permission
     *
     * @param int $permissionId
     * @return array ['hidden' => [...], 'masked' => [...], 'readonly' => [...]]
     */
    public static function getGroupedForPermission(int $permissionId): array
    {
        $restrictions = static::getForPermission($permissionId);

        $grouped = [
            self::TYPE_HIDDEN => [],
            self::TYPE_MASKED => [],
            self::TYPE_READONLY => [],
        ];

        foreach ($restrictions as $restriction) {
            $type = $restriction['restriction_type'];
            $grouped[$type][] = $restriction;
        }

        return $grouped;
    }
}
