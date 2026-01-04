<?php

declare(strict_types=1);

namespace Conduit\Authorization\Models;

use Conduit\Database\Model;
use Conduit\Database\Relations\BelongsTo;

/**
 * Permission Policy Model
 *
 * Represents dynamic authorization rules/conditions for a permission.
 * Allows record-level and attribute-based access control.
 *
 * Policy Types:
 * - ownership: User owns the resource (user_id = auth.id)
 * - team: User is in same team as resource
 * - department: User is in same department
 * - custom: Custom JSON-based rules
 *
 * Conditions Format (JSON):
 * {
 *     "field": "user_id",
 *     "operator": "equals",
 *     "value": "{auth.id}"
 * }
 *
 * @property int $id
 * @property int $permission_id
 * @property string $policy_type
 * @property string $conditions JSON conditions
 * @property int $priority
 * @property int $created_at
 * @property int $updated_at
 *
 * @package Conduit\Authorization\Models
 */
class PermissionPolicy extends Model
{
    /**
     * Table name
     *
     * @var string
     */
    protected string $table = 'permission_policies';

    /**
     * Mass assignment fillable attributes
     *
     * @var array
     */
    protected array $fillable = [
        'permission_id',
        'policy_type',
        'conditions',
        'priority',
    ];

    /**
     * Attribute casts
     *
     * @var array
     */
    protected array $casts = [
        'id' => 'int',
        'permission_id' => 'int',
        'priority' => 'int',
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
     * Get conditions as array
     *
     * @return array
     */
    public function getConditionsArray(): array
    {
        return json_decode($this->conditions ?? '[]', true) ?? [];
    }

    /**
     * Set conditions from array
     *
     * @param array $conditions
     * @return self
     */
    public function setConditionsArray(array $conditions): self
    {
        $this->conditions = json_encode($conditions);
        return $this;
    }

    /**
     * Create an ownership policy
     *
     * Creates a policy that checks if user owns the resource.
     *
     * @param int $permissionId
     * @param string $ownerField Field name that contains owner ID (default: 'user_id')
     * @param int $priority
     * @return self
     */
    public static function createOwnershipPolicy(
        int $permissionId,
        string $ownerField = 'user_id',
        int $priority = 100
    ): self {
        return static::create([
            'permission_id' => $permissionId,
            'policy_type' => 'ownership',
            'conditions' => json_encode([
                'field' => $ownerField,
                'operator' => 'equals',
                'value' => '{auth.id}',
            ]),
            'priority' => $priority,
        ]);
    }

    /**
     * Create a team-based policy
     *
     * Creates a policy that checks if user is in same team.
     *
     * @param int $permissionId
     * @param string $teamField Field name that contains team ID (default: 'team_id')
     * @param int $priority
     * @return self
     */
    public static function createTeamPolicy(
        int $permissionId,
        string $teamField = 'team_id',
        int $priority = 80
    ): self {
        return static::create([
            'permission_id' => $permissionId,
            'policy_type' => 'team',
            'conditions' => json_encode([
                'field' => $teamField,
                'operator' => 'equals',
                'value' => '{auth.team_id}',
            ]),
            'priority' => $priority,
        ]);
    }

    /**
     * Create a department-based policy
     *
     * Creates a policy that checks if user is in same department.
     *
     * @param int $permissionId
     * @param string $departmentField Field name that contains department ID (default: 'department_id')
     * @param int $priority
     * @return self
     */
    public static function createDepartmentPolicy(
        int $permissionId,
        string $departmentField = 'department_id',
        int $priority = 60
    ): self {
        return static::create([
            'permission_id' => $permissionId,
            'policy_type' => 'department',
            'conditions' => json_encode([
                'field' => $departmentField,
                'operator' => 'equals',
                'value' => '{auth.department_id}',
            ]),
            'priority' => $priority,
        ]);
    }

    /**
     * Create a custom policy
     *
     * @param int $permissionId
     * @param array $conditions Custom conditions array
     * @param int $priority
     * @return self
     */
    public static function createCustomPolicy(
        int $permissionId,
        array $conditions,
        int $priority = 50
    ): self {
        return static::create([
            'permission_id' => $permissionId,
            'policy_type' => 'custom',
            'conditions' => json_encode($conditions),
            'priority' => $priority,
        ]);
    }

    /**
     * Check if policy type is ownership
     *
     * @return bool
     */
    public function isOwnershipPolicy(): bool
    {
        return $this->policy_type === 'ownership';
    }

    /**
     * Check if policy type is team
     *
     * @return bool
     */
    public function isTeamPolicy(): bool
    {
        return $this->policy_type === 'team';
    }

    /**
     * Check if policy type is department
     *
     * @return bool
     */
    public function isDepartmentPolicy(): bool
    {
        return $this->policy_type === 'department';
    }

    /**
     * Check if policy type is custom
     *
     * @return bool
     */
    public function isCustomPolicy(): bool
    {
        return $this->policy_type === 'custom';
    }
}
