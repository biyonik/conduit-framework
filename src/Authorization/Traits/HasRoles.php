<?php

declare(strict_types=1);

namespace Conduit\Authorization\Traits;

use Conduit\Authorization\Models\Role;
use Conduit\Authorization\Models\Permission;
use Conduit\Database\Relations\BelongsToMany;

/**
 * HasRoles Trait
 *
 * Provides role and permission functionality to User models.
 * Add this trait to your User model to enable RBAC.
 *
 * Usage:
 * ```php
 * class User extends Model
 * {
 *     use HasRoles;
 * }
 *
 * $user->assignRole('admin');
 * if ($user->can('posts.delete')) {
 *     // User has permission
 * }
 * ```
 *
 * @package Conduit\Authorization\Traits
 */
trait HasRoles
{
    /**
     * Cache for loaded permissions
     *
     * @var array|null
     */
    protected ?array $cachedPermissions = null;

    /**
     * Cache for loaded roles
     *
     * @var array|null
     */
    protected ?array $cachedRoles = null;

    /**
     * Roles relationship
     *
     * A user can have many roles (many-to-many).
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_user',
            'user_id',
            'role_id'
        );
    }

    /**
     * Assign a role to the user
     *
     * @param Role|int|string $role Role instance, ID, or slug
     * @return self
     */
    public function assignRole(Role|int|string $role): self
    {
        $roleModel = $this->resolveRole($role);

        if (!$this->hasRole($roleModel)) {
            $this->roles()->attach($roleModel->getKey());
            $this->clearPermissionCache();
        }

        return $this;
    }

    /**
     * Remove a role from the user
     *
     * @param Role|int|string $role Role instance, ID, or slug
     * @return self
     */
    public function removeRole(Role|int|string $role): self
    {
        $roleModel = $this->resolveRole($role);

        $this->roles()->detach($roleModel->getKey());
        $this->clearPermissionCache();

        return $this;
    }

    /**
     * Sync user's roles
     *
     * @param array $roles Array of Role instances, IDs, or slugs
     * @return self
     */
    public function syncRoles(array $roles): self
    {
        $roleIds = array_map(
            fn($role) => $this->resolveRole($role)->getKey(),
            $roles
        );

        $this->roles()->sync($roleIds);
        $this->clearPermissionCache();

        return $this;
    }

    /**
     * Check if user has a specific role
     *
     * @param Role|int|string|array $role Role instance, ID, slug, or array of roles
     * @param bool $requireAll If true, user must have ALL roles (AND logic)
     * @return bool
     */
    public function hasRole(Role|int|string|array $role, bool $requireAll = false): bool
    {
        if (is_array($role)) {
            $checks = array_map(fn($r) => $this->hasRole($r), $role);
            return $requireAll ? !in_array(false, $checks, true) : in_array(true, $checks, true);
        }

        $roleModel = $this->resolveRole($role);

        return $this->roles()
            ->where('roles.id', '=', $roleModel->getKey())
            ->exists();
    }

    /**
     * Check if user has ANY of the given roles (OR logic)
     *
     * @param array $roles Array of Role instances, IDs, or slugs
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->hasRole($roles, false);
    }

    /**
     * Check if user has ALL of the given roles (AND logic)
     *
     * @param array $roles Array of Role instances, IDs, or slugs
     * @return bool
     */
    public function hasAllRoles(array $roles): bool
    {
        return $this->hasRole($roles, true);
    }

    /**
     * Check if user has a specific permission
     *
     * This checks permissions through the user's roles.
     *
     * @param Permission|int|string|array $permission Permission instance, ID, name, or array
     * @param bool $requireAll If true, user must have ALL permissions (AND logic)
     * @return bool
     */
    public function hasPermissionTo(Permission|int|string|array $permission, bool $requireAll = false): bool
    {
        if (is_array($permission)) {
            $checks = array_map(fn($p) => $this->hasPermissionTo($p), $permission);
            return $requireAll ? !in_array(false, $checks, true) : in_array(true, $checks, true);
        }

        $permissionModel = $this->resolvePermission($permission);

        // Check in cached permissions
        $userPermissions = $this->getAllPermissions();

        foreach ($userPermissions as $perm) {
            if ($perm['id'] === $permissionModel->getKey()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alias for hasPermissionTo (Laravel-like syntax)
     *
     * @param Permission|int|string|array $permission
     * @return bool
     */
    public function can(Permission|int|string|array $permission): bool
    {
        return $this->hasPermissionTo($permission);
    }

    /**
     * Check if user does NOT have a permission
     *
     * @param Permission|int|string|array $permission
     * @return bool
     */
    public function cannot(Permission|int|string|array $permission): bool
    {
        return !$this->can($permission);
    }

    /**
     * Get all permissions for the user (through roles)
     *
     * @return array
     */
    public function getAllPermissions(): array
    {
        if ($this->cachedPermissions !== null) {
            return $this->cachedPermissions;
        }

        $permissions = [];

        foreach ($this->roles()->get() as $role) {
            foreach ($role->permissions()->get() as $permission) {
                $permissions[$permission->getKey()] = $permission->toArray();
            }
        }

        $this->cachedPermissions = array_values($permissions);

        return $this->cachedPermissions;
    }

    /**
     * Get all permission names for the user
     *
     * @return array
     */
    public function getPermissionNames(): array
    {
        return array_column($this->getAllPermissions(), 'name');
    }

    /**
     * Get all role names for the user
     *
     * @return array
     */
    public function getRoleNames(): array
    {
        if ($this->cachedRoles !== null) {
            return $this->cachedRoles;
        }

        $this->cachedRoles = $this->roles()->pluck('name')->toArray();

        return $this->cachedRoles;
    }

    /**
     * Clear permission cache
     *
     * Call this after role/permission changes.
     *
     * @return void
     */
    public function clearPermissionCache(): void
    {
        $this->cachedPermissions = null;
        $this->cachedRoles = null;
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
            $found = Permission::findByName($permission);
            if (!$found) {
                throw new \InvalidArgumentException("Permission with name '{$permission}' not found");
            }
            return $found;
        }

        throw new \InvalidArgumentException('Permission must be an instance, ID, or name string');
    }
}
