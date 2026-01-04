<?php

declare(strict_types=1);

namespace Conduit\Authorization\Traits;

use Conduit\Authorization\Models\Permission;
use Conduit\Database\QueryBuilder;
use Conduit\Database\Model;

/**
 * AppliesPermissionScopes Trait
 *
 * Automatically applies permission-based query scopes to models.
 * This enables automatic row-level security (RLS) filtering.
 *
 * Usage:
 * ```php
 * class Post extends Model
 * {
 *     use AppliesPermissionScopes;
 *
 *     protected string $permissionResource = 'posts';
 * }
 *
 * // Automatically filters posts based on user's 'posts.view' permission policies
 * $posts = Post::forUser($user)->get();
 * ```
 *
 * @package Conduit\Authorization\Traits
 */
trait AppliesPermissionScopes
{
    /**
     * Permission resource name (override in model)
     *
     * @var string
     */
    protected string $permissionResource = '';

    /**
     * Apply permission scope for a user and action
     *
     * @param QueryBuilder $query
     * @param Model $user User with HasRoles trait
     * @param string $action Permission action (default: 'view')
     * @return QueryBuilder
     */
    public function scopeForUser(QueryBuilder $query, Model $user, string $action = 'view'): QueryBuilder
    {
        $resource = $this->getPermissionResource();
        $permissionName = Permission::generateName($resource, $action);

        // Check if user has base permission
        if (!$user->hasPermissionTo($permissionName)) {
            // No permission - return empty result set
            return $query->whereRaw('1 = 0');
        }

        // Get permission and its policies
        $permission = Permission::findByName($permissionName);
        if (!$permission || !$permission->hasPolicies()) {
            // No policies - user can see all records
            return $query;
        }

        // Apply policy filters
        return $this->applyPolicyScopes($query, $permission, $user);
    }

    /**
     * Apply policy-based query scopes
     *
     * @param QueryBuilder $query
     * @param Permission $permission
     * @param Model $user
     * @return QueryBuilder
     */
    protected function applyPolicyScopes(QueryBuilder $query, Permission $permission, Model $user): QueryBuilder
    {
        $policies = $permission->getPoliciesOrdered();

        if (empty($policies)) {
            return $query;
        }

        // Build OR conditions for each policy
        $query->where(function (QueryBuilder $q) use ($policies, $user) {
            foreach ($policies as $policyData) {
                $conditions = json_decode($policyData['conditions'] ?? '[]', true);

                $q->orWhere(function (QueryBuilder $subQuery) use ($conditions, $user, $policyData) {
                    $this->applyPolicyCondition($subQuery, $conditions, $user, $policyData['policy_type']);
                });
            }
        });

        return $query;
    }

    /**
     * Apply a single policy condition to query
     *
     * @param QueryBuilder $query
     * @param array $conditions
     * @param Model $user
     * @param string $policyType
     * @return void
     */
    protected function applyPolicyCondition(
        QueryBuilder $query,
        array $conditions,
        Model $user,
        string $policyType
    ): void {
        // Handle simple condition
        if (isset($conditions['field'])) {
            $field = $conditions['field'];
            $operator = $this->mapOperatorToSql($conditions['operator'] ?? 'equals');
            $value = $this->resolveConditionValue($conditions['value'] ?? null, $user);

            $query->where($field, $operator, $value);
            return;
        }

        // Handle compound conditions
        if (isset($conditions['and'])) {
            foreach ($conditions['and'] as $condition) {
                $query->where(function (QueryBuilder $subQuery) use ($condition, $user, $policyType) {
                    $this->applyPolicyCondition($subQuery, $condition, $user, $policyType);
                });
            }
        }

        if (isset($conditions['or'])) {
            $query->where(function (QueryBuilder $subQuery) use ($conditions, $user, $policyType) {
                foreach ($conditions['or'] as $condition) {
                    $subQuery->orWhere(function (QueryBuilder $q) use ($condition, $user, $policyType) {
                        $this->applyPolicyCondition($q, $condition, $user, $policyType);
                    });
                }
            });
        }
    }

    /**
     * Map authorization operator to SQL operator
     *
     * @param string $operator
     * @return string
     */
    protected function mapOperatorToSql(string $operator): string
    {
        return match ($operator) {
            'equals', 'strict_equals' => '=',
            'not_equals' => '!=',
            'greater_than' => '>',
            'greater_than_or_equal' => '>=',
            'less_than' => '<',
            'less_than_or_equal' => '<=',
            'in' => 'IN',
            'not_in' => 'NOT IN',
            'contains', 'like' => 'LIKE',
            'is_null' => 'IS NULL',
            'is_not_null' => 'IS NOT NULL',
            default => '=',
        };
    }

    /**
     * Resolve dynamic condition values
     *
     * @param mixed $value
     * @param Model $user
     * @return mixed
     */
    protected function resolveConditionValue(mixed $value, Model $user): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // Handle {auth.field} placeholders
        if (preg_match('/^\{auth\.(.+)\}$/', $value, $matches)) {
            $field = $matches[1];
            return $user->getAttribute($field);
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
     * Get permission resource name
     *
     * @return string
     */
    protected function getPermissionResource(): string
    {
        if (!empty($this->permissionResource)) {
            return $this->permissionResource;
        }

        // Auto-generate from table name
        return rtrim($this->getTable(), 's'); // Remove trailing 's' for singular
    }

    /**
     * Scope to filter by ownership
     *
     * Convenience method for common ownership filtering.
     *
     * @param QueryBuilder $query
     * @param Model $user
     * @param string $ownerField Field name containing owner ID (default: 'user_id')
     * @return QueryBuilder
     */
    public function scopeOwnedBy(QueryBuilder $query, Model $user, string $ownerField = 'user_id'): QueryBuilder
    {
        return $query->where($ownerField, '=', $user->getKey());
    }

    /**
     * Scope to filter by team
     *
     * @param QueryBuilder $query
     * @param Model $user
     * @param string $teamField Field name containing team ID (default: 'team_id')
     * @return QueryBuilder
     */
    public function scopeInTeam(QueryBuilder $query, Model $user, string $teamField = 'team_id'): QueryBuilder
    {
        $userTeamId = $user->getAttribute('team_id');
        if ($userTeamId === null) {
            return $query->whereRaw('1 = 0'); // No team, no results
        }

        return $query->where($teamField, '=', $userTeamId);
    }

    /**
     * Scope to filter by department
     *
     * @param QueryBuilder $query
     * @param Model $user
     * @param string $departmentField Field name containing department ID (default: 'department_id')
     * @return QueryBuilder
     */
    public function scopeInDepartment(QueryBuilder $query, Model $user, string $departmentField = 'department_id'): QueryBuilder
    {
        $userDeptId = $user->getAttribute('department_id');
        if ($userDeptId === null) {
            return $query->whereRaw('1 = 0'); // No department, no results
        }

        return $query->where($departmentField, '=', $userDeptId);
    }
}
