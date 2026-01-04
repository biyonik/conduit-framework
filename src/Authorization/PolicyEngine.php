<?php

declare(strict_types=1);

namespace Conduit\Authorization;

use Conduit\Authorization\Models\Permission;
use Conduit\Authorization\Models\PermissionPolicy;
use Conduit\Database\Model;

/**
 * Policy Engine
 *
 * Core authorization engine that evaluates dynamic policies and conditions.
 * Handles record-level, attribute-based access control.
 *
 * Usage:
 * ```php
 * $engine = new PolicyEngine($user);
 * if ($engine->authorize('posts.delete', $post)) {
 *     // User can delete this specific post
 * }
 * ```
 *
 * @package Conduit\Authorization
 */
class PolicyEngine
{
    /**
     * Authenticated user
     *
     * @var Model
     */
    protected Model $user;

    /**
     * User permissions cache
     *
     * @var array|null
     */
    protected ?array $userPermissions = null;

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
     * Authorize an action on a resource
     *
     * @param string $permissionName Permission name (e.g., 'posts.delete')
     * @param Model|null $resource Optional resource to check against (for record-level auth)
     * @return bool
     */
    public function authorize(string $permissionName, ?Model $resource = null): bool
    {
        // Step 1: Check if user has the base permission through roles
        if (!$this->user->hasPermissionTo($permissionName)) {
            return false;
        }

        // Step 2: If no resource provided, permission check passes (table-level permission)
        if ($resource === null) {
            return true;
        }

        // Step 3: Get permission and its policies
        $permission = Permission::findByName($permissionName);
        if (!$permission) {
            return false;
        }

        // Step 4: If permission has no policies, allow (table-level only)
        if (!$permission->hasPolicies()) {
            return true;
        }

        // Step 5: Evaluate policies (record-level authorization)
        return $this->evaluatePolicies($permission, $resource);
    }

    /**
     * Evaluate policies for a permission and resource
     *
     * @param Permission $permission
     * @param Model $resource
     * @return bool
     */
    protected function evaluatePolicies(Permission $permission, Model $resource): bool
    {
        $policies = $permission->getPoliciesOrdered();

        // If any policy passes, authorization succeeds (OR logic between policies)
        foreach ($policies as $policyData) {
            $policy = new PermissionPolicy($policyData);

            if ($this->evaluatePolicy($policy, $resource)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate a single policy
     *
     * @param PermissionPolicy $policy
     * @param Model $resource
     * @return bool
     */
    protected function evaluatePolicy(PermissionPolicy $policy, Model $resource): bool
    {
        $conditions = $policy->getConditionsArray();

        // Handle different policy types
        if ($policy->isOwnershipPolicy()) {
            return $this->evaluateOwnershipPolicy($conditions, $resource);
        }

        if ($policy->isTeamPolicy()) {
            return $this->evaluateTeamPolicy($conditions, $resource);
        }

        if ($policy->isDepartmentPolicy()) {
            return $this->evaluateDepartmentPolicy($conditions, $resource);
        }

        if ($policy->isCustomPolicy()) {
            return $this->evaluateCustomPolicy($conditions, $resource);
        }

        return false;
    }

    /**
     * Evaluate ownership policy
     *
     * Checks if user owns the resource.
     *
     * @param array $conditions
     * @param Model $resource
     * @return bool
     */
    protected function evaluateOwnershipPolicy(array $conditions, Model $resource): bool
    {
        $field = $conditions['field'] ?? 'user_id';
        $operator = $conditions['operator'] ?? 'equals';
        $value = $conditions['value'] ?? '{auth.id}';

        $resourceValue = $resource->getAttribute($field);
        $compareValue = $this->resolveValue($value);

        return $this->compareValues($resourceValue, $operator, $compareValue);
    }

    /**
     * Evaluate team policy
     *
     * Checks if user is in same team as resource.
     *
     * @param array $conditions
     * @param Model $resource
     * @return bool
     */
    protected function evaluateTeamPolicy(array $conditions, Model $resource): bool
    {
        $field = $conditions['field'] ?? 'team_id';
        $operator = $conditions['operator'] ?? 'equals';
        $value = $conditions['value'] ?? '{auth.team_id}';

        $resourceValue = $resource->getAttribute($field);
        $compareValue = $this->resolveValue($value);

        return $this->compareValues($resourceValue, $operator, $compareValue);
    }

    /**
     * Evaluate department policy
     *
     * Checks if user is in same department as resource.
     *
     * @param array $conditions
     * @param Model $resource
     * @return bool
     */
    protected function evaluateDepartmentPolicy(array $conditions, Model $resource): bool
    {
        $field = $conditions['field'] ?? 'department_id';
        $operator = $conditions['operator'] ?? 'equals';
        $value = $conditions['value'] ?? '{auth.department_id}';

        $resourceValue = $resource->getAttribute($field);
        $compareValue = $this->resolveValue($value);

        return $this->compareValues($resourceValue, $operator, $compareValue);
    }

    /**
     * Evaluate custom policy
     *
     * Evaluates complex custom conditions.
     *
     * @param array $conditions
     * @param Model $resource
     * @return bool
     */
    protected function evaluateCustomPolicy(array $conditions, Model $resource): bool
    {
        // Handle simple condition
        if (isset($conditions['field'])) {
            $field = $conditions['field'];
            $operator = $conditions['operator'] ?? 'equals';
            $value = $conditions['value'] ?? null;

            $resourceValue = $resource->getAttribute($field);
            $compareValue = $this->resolveValue($value);

            return $this->compareValues($resourceValue, $operator, $compareValue);
        }

        // Handle compound conditions (AND/OR)
        if (isset($conditions['and'])) {
            // All conditions must pass (AND logic)
            foreach ($conditions['and'] as $condition) {
                if (!$this->evaluateCustomPolicy($condition, $resource)) {
                    return false;
                }
            }
            return true;
        }

        if (isset($conditions['or'])) {
            // Any condition must pass (OR logic)
            foreach ($conditions['or'] as $condition) {
                if ($this->evaluateCustomPolicy($condition, $resource)) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Compare two values using an operator
     *
     * @param mixed $left Left value
     * @param string $operator Comparison operator
     * @param mixed $right Right value
     * @return bool
     */
    protected function compareValues(mixed $left, string $operator, mixed $right): bool
    {
        return match ($operator) {
            'equals', '=', '==' => $left == $right,
            'strict_equals', '===' => $left === $right,
            'not_equals', '!=', '<>' => $left != $right,
            'greater_than', '>' => $left > $right,
            'greater_than_or_equal', '>=' => $left >= $right,
            'less_than', '<' => $left < $right,
            'less_than_or_equal', '<=' => $left <= $right,
            'in' => is_array($right) && in_array($left, $right, true),
            'not_in' => is_array($right) && !in_array($left, $right, true),
            'contains' => is_string($left) && is_string($right) && str_contains($left, $right),
            'starts_with' => is_string($left) && is_string($right) && str_starts_with($left, $right),
            'ends_with' => is_string($left) && is_string($right) && str_ends_with($left, $right),
            'is_null' => $left === null,
            'is_not_null' => $left !== null,
            default => false,
        };
    }

    /**
     * Resolve dynamic values (e.g., {auth.id})
     *
     * @param mixed $value
     * @return mixed
     */
    protected function resolveValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // Handle {auth.field} placeholders
        if (preg_match('/^\{auth\.(.+)\}$/', $value, $matches)) {
            $field = $matches[1];
            return $this->user->getAttribute($field);
        }

        // Handle {now} placeholder
        if ($value === '{now}') {
            return time();
        }

        // Handle {today} placeholder
        if ($value === '{today}') {
            return strtotime('today');
        }

        return $value;
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
     * Check multiple permissions at once (AND logic)
     *
     * @param array $permissions Array of permission names
     * @param Model|null $resource
     * @return bool
     */
    public function authorizeAll(array $permissions, ?Model $resource = null): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->authorize($permission, $resource)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check multiple permissions at once (OR logic)
     *
     * @param array $permissions Array of permission names
     * @param Model|null $resource
     * @return bool
     */
    public function authorizeAny(array $permissions, ?Model $resource = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->authorize($permission, $resource)) {
                return true;
            }
        }
        return false;
    }
}
