<?php

declare(strict_types=1);

namespace Conduit\Authorization;

use Conduit\Authorization\Models\Permission;
use Conduit\Authorization\Models\FieldRestriction;
use Conduit\Database\Model;

/**
 * Field Restrictor
 *
 * Handles field-level (column-level) security.
 * Applies restrictions like hiding, masking, or marking fields as read-only.
 *
 * Usage:
 * ```php
 * $restrictor = new FieldRestrictor($user);
 * $sanitized = $restrictor->applyRestrictions('posts.view', $post);
 * // Sensitive fields are hidden/masked based on user's permissions
 * ```
 *
 * @package Conduit\Authorization
 */
class FieldRestrictor
{
    /**
     * Authenticated user
     *
     * @var Model
     */
    protected Model $user;

    /**
     * Cache for field restrictions
     *
     * @var array
     */
    protected array $restrictionsCache = [];

    /**
     * Constructor
     *
     * @param Model $user Authenticated user with HasRoles trait
     */
    public function __construct(Model $user)
    {
        $this->user = $user;
    }

    /**
     * Apply field restrictions to a model instance
     *
     * @param string $permissionName Permission name (e.g., 'posts.view')
     * @param Model $model Model instance to restrict
     * @return array Sanitized attributes array
     */
    public function applyRestrictions(string $permissionName, Model $model): array
    {
        // Get all user permissions for this resource
        $restrictions = $this->getFieldRestrictionsForPermission($permissionName);

        if (empty($restrictions)) {
            // No restrictions - return all attributes
            return $model->toArray();
        }

        $attributes = $model->toArray();

        // Group restrictions by type
        $grouped = $this->groupRestrictionsByType($restrictions);

        // Apply hidden restrictions
        foreach ($grouped['hidden'] as $restriction) {
            unset($attributes[$restriction['field_name']]);
        }

        // Apply masked restrictions
        foreach ($grouped['masked'] as $restriction) {
            $fieldName = $restriction['field_name'];
            if (isset($attributes[$fieldName])) {
                $attributes[$fieldName] = $this->maskValue(
                    $attributes[$fieldName],
                    $restriction['mask_pattern'] ?? '***'
                );
            }
        }

        // Note: readonly restrictions are enforced during updates, not during reads

        return $attributes;
    }

    /**
     * Apply field restrictions to multiple model instances
     *
     * @param string $permissionName Permission name
     * @param array $models Array of Model instances
     * @return array Array of sanitized attributes arrays
     */
    public function applyRestrictionsToMany(string $permissionName, array $models): array
    {
        return array_map(
            fn(Model $model) => $this->applyRestrictions($permissionName, $model),
            $models
        );
    }

    /**
     * Check if a field can be modified (not readonly)
     *
     * @param string $permissionName Permission name (e.g., 'posts.update')
     * @param string $fieldName Field name to check
     * @return bool True if field can be modified
     */
    public function canModifyField(string $permissionName, string $fieldName): bool
    {
        $restrictions = $this->getFieldRestrictionsForPermission($permissionName);

        foreach ($restrictions as $restriction) {
            if (
                $restriction['field_name'] === $fieldName &&
                $restriction['restriction_type'] === FieldRestriction::TYPE_READONLY
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get list of fields that cannot be modified
     *
     * @param string $permissionName Permission name
     * @return array Array of field names
     */
    public function getReadonlyFields(string $permissionName): array
    {
        $restrictions = $this->getFieldRestrictionsForPermission($permissionName);
        $readonly = [];

        foreach ($restrictions as $restriction) {
            if ($restriction['restriction_type'] === FieldRestriction::TYPE_READONLY) {
                $readonly[] = $restriction['field_name'];
            }
        }

        return $readonly;
    }

    /**
     * Get list of hidden fields
     *
     * @param string $permissionName Permission name
     * @return array Array of field names
     */
    public function getHiddenFields(string $permissionName): array
    {
        $restrictions = $this->getFieldRestrictionsForPermission($permissionName);
        $hidden = [];

        foreach ($restrictions as $restriction) {
            if ($restriction['restriction_type'] === FieldRestriction::TYPE_HIDDEN) {
                $hidden[] = $restriction['field_name'];
            }
        }

        return $hidden;
    }

    /**
     * Filter input data to remove readonly fields
     *
     * Useful for preventing modification of readonly fields during updates.
     *
     * @param string $permissionName Permission name
     * @param array $inputData Input data array
     * @return array Filtered data with readonly fields removed
     */
    public function filterReadonlyFields(string $permissionName, array $inputData): array
    {
        $readonlyFields = $this->getReadonlyFields($permissionName);

        foreach ($readonlyFields as $field) {
            unset($inputData[$field]);
        }

        return $inputData;
    }

    /**
     * Get field restrictions for a permission
     *
     * @param string $permissionName
     * @return array
     */
    protected function getFieldRestrictionsForPermission(string $permissionName): array
    {
        // Check cache
        if (isset($this->restrictionsCache[$permissionName])) {
            return $this->restrictionsCache[$permissionName];
        }

        // Check if user has permission
        if (!$this->user->hasPermissionTo($permissionName)) {
            // No permission - hide all fields
            $this->restrictionsCache[$permissionName] = [];
            return [];
        }

        // Get permission
        $permission = Permission::findByName($permissionName);
        if (!$permission) {
            $this->restrictionsCache[$permissionName] = [];
            return [];
        }

        // Get field restrictions
        $restrictions = FieldRestriction::getForPermission($permission->getKey());

        $this->restrictionsCache[$permissionName] = $restrictions;

        return $restrictions;
    }

    /**
     * Group restrictions by type
     *
     * @param array $restrictions
     * @return array ['hidden' => [...], 'masked' => [...], 'readonly' => [...]]
     */
    protected function groupRestrictionsByType(array $restrictions): array
    {
        $grouped = [
            FieldRestriction::TYPE_HIDDEN => [],
            FieldRestriction::TYPE_MASKED => [],
            FieldRestriction::TYPE_READONLY => [],
        ];

        foreach ($restrictions as $restriction) {
            $type = $restriction['restriction_type'];
            $grouped[$type][] = $restriction;
        }

        return $grouped;
    }

    /**
     * Mask a value according to pattern
     *
     * @param mixed $value Original value
     * @param string $pattern Mask pattern
     * @return mixed Masked value
     */
    protected function maskValue(mixed $value, string $pattern): mixed
    {
        if ($value === null) {
            return null;
        }

        // Handle pattern with # (show last N characters)
        if (strpos($pattern, '#') !== false) {
            $showCount = substr_count($pattern, '#');
            $valueStr = (string)$value;
            $hideCount = max(0, strlen($valueStr) - $showCount);

            return str_repeat('*', $hideCount) . substr($valueStr, -$showCount);
        }

        // Simple replacement
        return $pattern;
    }

    /**
     * Get authenticated user
     *
     * @return Model
     */
    public function getUser(): Model
    {
        return $this->user;
    }

    /**
     * Clear restrictions cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->restrictionsCache = [];
    }
}
