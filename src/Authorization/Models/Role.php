<?php

declare(strict_types=1);

namespace Conduit\Authorization\Models;

use Conduit\Database\Model;
use Conduit\Database\Relations\BelongsToMany;

/**
 * Role Model
 *
 * Represents a role in the RBAC system.
 * Roles can have multiple permissions and be assigned to multiple users.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $created_at
 * @property int $updated_at
 *
 * @package Conduit\Authorization\Models
 */
class Role extends Model
{
    /**
     * Table name
     *
     * @var string
     */
    protected string $table = 'roles';

    /**
     * Mass assignment fillable attributes
     *
     * @var array
     */
    protected array $fillable = [
        'name',
        'slug',
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
     * Permissions relationship
     *
     * A role can have many permissions (many-to-many).
     *
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'permission_role',
            'role_id',
            'permission_id'
        );
    }

    /**
     * Users relationship
     *
     * A role can be assigned to many users (many-to-many).
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            'Conduit\Database\Model', // Generic - will be User model
            'role_user',
            'role_id',
            'user_id'
        );
    }

    /**
     * Grant a permission to this role
     *
     * @param Permission|int|string $permission Permission instance, ID, or name
     * @return self
     */
    public function givePermissionTo(Permission|int|string $permission): self
    {
        $permissionModel = $this->resolvePermission($permission);

        if (!$this->hasPermissionTo($permissionModel)) {
            $this->permissions()->attach($permissionModel->getKey());
        }

        return $this;
    }

    /**
     * Revoke a permission from this role
     *
     * @param Permission|int|string $permission Permission instance, ID, or name
     * @return self
     */
    public function revokePermissionTo(Permission|int|string $permission): self
    {
        $permissionModel = $this->resolvePermission($permission);

        $this->permissions()->detach($permissionModel->getKey());

        return $this;
    }

    /**
     * Sync permissions for this role
     *
     * @param array $permissions Array of Permission instances, IDs, or names
     * @return self
     */
    public function syncPermissions(array $permissions): self
    {
        $permissionIds = array_map(
            fn($permission) => $this->resolvePermission($permission)->getKey(),
            $permissions
        );

        $this->permissions()->sync($permissionIds);

        return $this;
    }

    /**
     * Check if role has a specific permission
     *
     * @param Permission|int|string $permission Permission instance, ID, or name
     * @return bool
     */
    public function hasPermissionTo(Permission|int|string $permission): bool
    {
        $permissionModel = $this->resolvePermission($permission);

        return $this->permissions()
            ->where('permissions.id', '=', $permissionModel->getKey())
            ->exists();
    }

    /**
     * Get all permission names for this role
     *
     * @return array
     */
    public function getPermissionNames(): array
    {
        return $this->permissions()->pluck('name')->toArray();
    }

    /**
     * Resolve permission from various input types
     *
     * @param Permission|int|string $permission
     * @return Permission
     * @throws \InvalidArgumentException
     */
    protected function resolvePermission(Permission|int|string $permission): Permission
    {
        if ($permission instanceof Permission) {
            return $permission;
        }

        if (is_int($permission)) {
            $found = Permission::find($permission);
            if (!$found) {
                throw new \InvalidArgumentException("Permission with ID {$permission} not found");
            }
            return $found;
        }

        if (is_string($permission)) {
            $found = Permission::where('name', '=', $permission)->first();
            if (!$found) {
                throw new \InvalidArgumentException("Permission with name '{$permission}' not found");
            }
            return $found;
        }

        throw new \InvalidArgumentException('Permission must be an instance, ID, or name string');
    }

    /**
     * Find role by slug
     *
     * @param string $slug
     * @return self|null
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', '=', $slug)->first();
    }

    /**
     * Find role by name
     *
     * @param string $name
     * @return self|null
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', '=', $name)->first();
    }
}
