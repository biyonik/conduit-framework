<?php

declare(strict_types=1);

namespace App\Models;

use Conduit\Database\Model;
use Conduit\Authorization\Traits\HasRoles;

/**
 * User Model (Example)
 *
 * This is an example User model demonstrating how to integrate
 * the RBAC system into your application.
 *
 * To use RBAC in your application:
 * 1. Add the HasRoles trait to your User model
 * 2. Run migrations to create RBAC tables
 * 3. Seed initial roles and permissions
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property int|null $team_id
 * @property int|null $department_id
 * @property int $created_at
 * @property int $updated_at
 *
 * @package App\Models
 */
class User extends Model
{
    use HasRoles;

    /**
     * Table name
     *
     * @var string
     */
    protected string $table = 'users';

    /**
     * Mass assignment fillable attributes
     *
     * @var array
     */
    protected array $fillable = [
        'name',
        'email',
        'password',
        'team_id',
        'department_id',
    ];

    /**
     * Guarded attributes (cannot be mass assigned)
     *
     * @var array
     */
    protected array $guarded = [
        'id',
    ];

    /**
     * Hidden attributes (not included in toArray/toJson)
     *
     * @var array
     */
    protected array $hidden = [
        'password',
    ];

    /**
     * Attribute casts
     *
     * @var array
     */
    protected array $casts = [
        'id' => 'int',
        'team_id' => 'int',
        'department_id' => 'int',
        'created_at' => 'int',
        'updated_at' => 'int',
    ];

    /**
     * Example: Check if user is admin
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('super-admin');
    }

    /**
     * Example: Check if user can manage posts
     *
     * @return bool
     */
    public function canManagePosts(): bool
    {
        return $this->hasAnyRole(['admin', 'manager']);
    }

    /**
     * Example: Get user's team
     *
     * @return \Conduit\Database\Relations\BelongsTo
     */
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Example: Get user's department
     *
     * @return \Conduit\Database\Relations\BelongsTo
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
