<?php

declare(strict_types=1);

namespace Conduit\Authorization\Models;

use Conduit\Database\Model;
use Conduit\Database\Relations\BelongsToMany;
use Conduit\Database\Relations\HasMany;

/**
 * Permission Model
 *
 * Represents a permission in the RBAC system.
 * Permissions define what actions can be performed on specific resources.
 *
 * @property int $id
 * @property string $resource
 * @property string $action
 * @property string $name
 * @property string|null $description
 * @property int $created_at
 * @property int $updated_at
 *
 * @package Conduit\Authorization\Models
 */
class Permission extends Model
{
    /**
     * Table name
     *
     * @var string
     */
    protected string $table = 'permissions';

    /**
     * Mass assignment fillable attributes
     *
     * @var array
     */
    protected array $fillable = [
        'resource',
        'action',
        'name',
        'description',
    ];

    /**
     * Attribute casts
     *
     * @var array
     */
    protected array $casts = [
        'id' => 'int',
        'created_at' => 'int',
        'updated_at' => 'int',
    ];

    /**
     * Roles relationship
     *
     * A permission can belong to many roles (many-to-many).
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'permission_role',
            'permission_id',
            'role_id'
        );
    }

    /**
     * Policies relationship
     *
     * A permission can have multiple policies (conditions).
     *
     * @return HasMany
     */
    public function policies(): HasMany
    {
        return $this->hasMany(PermissionPolicy::class, 'permission_id');
    }

    /**
     * Field restrictions relationship
     *
     * A permission can have field-level restrictions.
     *
     * @return HasMany
     */
    public function fieldRestrictions(): HasMany
    {
        return $this->hasMany(FieldRestriction::class, 'permission_id');
    }

    /**
     * Create or get a permission by resource and action
     *
     * @param string $resource
     * @param string $action
     * @param string|null $description
     * @return self
     */
    public static function createOrGet(string $resource, string $action, ?string $description = null): self
    {
        $name = static::generateName($resource, $action);

        $permission = static::where('name', '=', $name)->first();

        if (!$permission) {
            $permission = static::create([
                'resource' => $resource,
                'action' => $action,
                'name' => $name,
                'description' => $description,
            ]);
        }

        return $permission;
    }

    /**
     * Find permission by name
     *
     * @param string $name
     * @return self|null
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', '=', $name)->first();
    }

    /**
     * Find permissions by resource
     *
     * @param string $resource
     * @return array
     */
    public static function findByResource(string $resource): array
    {
        return static::where('resource', '=', $resource)->get()->toArray();
    }

    /**
     * Add a policy (condition) to this permission
     *
     * @param string $policyType 'ownership', 'team', 'department', 'custom'
     * @param array $conditions Conditions as associative array
     * @param int $priority Higher priority = checked first
     * @return PermissionPolicy
     */
    public function addPolicy(string $policyType, array $conditions, int $priority = 0): PermissionPolicy
    {
        return PermissionPolicy::create([
            'permission_id' => $this->getKey(),
            'policy_type' => $policyType,
            'conditions' => json_encode($conditions),
            'priority' => $priority,
        ]);
    }

    /**
     * Add a field restriction to this permission
     *
     * @param string $fieldName
     * @param string $restrictionType 'hidden', 'masked', 'readonly'
     * @param string|null $maskPattern Pattern for masking (e.g., '***', 'XXX-XX-####')
     * @return FieldRestriction
     */
    public function addFieldRestriction(
        string $fieldName,
        string $restrictionType = 'hidden',
        ?string $maskPattern = null
    ): FieldRestriction {
        return FieldRestriction::create([
            'permission_id' => $this->getKey(),
            'field_name' => $fieldName,
            'restriction_type' => $restrictionType,
            'mask_pattern' => $maskPattern,
        ]);
    }

    /**
     * Get all policies for this permission, ordered by priority
     *
     * @return array
     */
    public function getPoliciesOrdered(): array
    {
        return $this->policies()
            ->orderBy('priority', 'DESC')
            ->get()
            ->toArray();
    }

    /**
     * Check if this permission has any policies
     *
     * @return bool
     */
    public function hasPolicies(): bool
    {
        return $this->policies()->count() > 0;
    }

    /**
     * Check if this permission has field restrictions
     *
     * @return bool
     */
    public function hasFieldRestrictions(): bool
    {
        return $this->fieldRestrictions()->count() > 0;
    }

    /**
     * Get all restricted fields for this permission
     *
     * @return array Array of field names
     */
    public function getRestrictedFields(): array
    {
        return $this->fieldRestrictions()->pluck('field_name')->toArray();
    }

    /**
     * Generate permission name from resource and action
     *
     * @param string $resource
     * @param string $action
     * @return string
     */
    public static function generateName(string $resource, string $action): string
    {
        return strtolower($resource) . '.' . strtolower($action);
    }

    /**
     * Assign this permission to a role
     *
     * @param Role|int|string $role Role instance, ID, or slug
     * @return self
     */
    public function assignToRole(Role|int|string $role): self
    {
        $roleModel = $this->resolveRole($role);

        if (!$this->isAssignedToRole($roleModel)) {
            $this->roles()->attach($roleModel->getKey());
        }

        return $this;
    }

    /**
     * Remove this permission from a role
     *
     * @param Role|int|string $role Role instance, ID, or slug
     * @return self
     */
    public function removeFromRole(Role|int|string $role): self
    {
        $roleModel = $this->resolveRole($role);

        $this->roles()->detach($roleModel->getKey());

        return $this;
    }

    /**
     * Check if permission is assigned to a role
     *
     * @param Role|int|string $role
     * @return bool
     */
    public function isAssignedToRole(Role|int|string $role): bool
    {
        $roleModel = $this->resolveRole($role);

        return $this->roles()
            ->where('roles.id', '=', $roleModel->getKey())
            ->exists();
    }

    /**
     * Resolve role from various input types
     *
     * @param Role|int|string $role
     * @return Role
     * @throws \InvalidArgumentException
     */
    protected function resolveRole(Role|int|string $role): Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        if (is_int($role)) {
            $found = Role::find($role);
            if (!$found) {
                throw new \InvalidArgumentException("Role with ID {$role} not found");
            }
            return $found;
        }

        if (is_string($role)) {
            $found = Role::findBySlug($role);
            if (!$found) {
                throw new \InvalidArgumentException("Role with slug '{$role}' not found");
            }
            return $found;
        }

        throw new \InvalidArgumentException('Role must be an instance, ID, or slug string');
    }
}
