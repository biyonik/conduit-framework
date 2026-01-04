# RBAC System - Complete Usage Guide

## Table of Contents

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Basic Concepts](#basic-concepts)
4. [Setup](#setup)
5. [Usage Examples](#usage-examples)
6. [Advanced Features](#advanced-features)
7. [API Reference](#api-reference)

---

## Introduction

The Conduit Framework includes a powerful, flexible RBAC (Role-Based Access Control) system that provides:

- **Table-level permissions**: Control access to entire resources
- **Record-level permissions**: Dynamic policies based on ownership, team, department, or custom rules
- **Action-level permissions**: Fine-grained control over specific operations (view, create, update, delete, export, etc.)
- **Field-level permissions**: Hide, mask, or make specific fields read-only
- **Dynamic policy evaluation**: JSON-based rules for complex authorization logic

---

## Installation

### 1. Run Migrations

```bash
php conduit migrate
```

This creates the following tables:
- `roles` - User roles
- `permissions` - Available permissions
- `permission_policies` - Dynamic authorization rules
- `field_restrictions` - Field-level security
- `role_user` - User-role assignments
- `permission_role` - Role-permission assignments

### 2. Register Service Provider

In your `config/app.php`:

```php
'providers' => [
    // ... other providers
    \Conduit\Authorization\AuthorizationServiceProvider::class,
],
```

### 3. Seed Initial Data

```bash
php conduit db:seed RBACSeeder
```

---

## Basic Concepts

### Roles

Roles group permissions together. Examples: `admin`, `manager`, `user`

### Permissions

Permissions are defined as `resource.action`:
- `posts.view` - View posts
- `posts.create` - Create posts
- `posts.update` - Update posts
- `posts.delete` - Delete posts
- `users.export` - Export user data

### Policies

Policies add conditional logic to permissions:
- **Ownership**: User owns the resource
- **Team**: User is in same team
- **Department**: User is in same department
- **Custom**: JSON-based rules

### Field Restrictions

Control field-level access:
- **Hidden**: Field is removed from output
- **Masked**: Field value is masked (e.g., `****1234`)
- **Readonly**: Field can be viewed but not modified

---

## Setup

### 1. Add HasRoles Trait to User Model

```php
<?php

namespace App\Models;

use Conduit\Database\Model;
use Conduit\Authorization\Traits\HasRoles;

class User extends Model
{
    use HasRoles;

    protected string $table = 'users';

    protected array $fillable = [
        'name',
        'email',
        'password',
        'team_id',
        'department_id',
    ];
}
```

### 2. Create Roles

```php
use Conduit\Authorization\Models\Role;

$admin = Role::create([
    'name' => 'Administrator',
    'slug' => 'admin',
    'description' => 'Full system access',
]);

$manager = Role::create([
    'name' => 'Manager',
    'slug' => 'manager',
    'description' => 'Management access',
]);

$user = Role::create([
    'name' => 'User',
    'slug' => 'user',
    'description' => 'Basic user access',
]);
```

### 3. Create Permissions

```php
use Conduit\Authorization\Models\Permission;

$viewPosts = Permission::createOrGet('posts', 'view', 'View posts');
$createPosts = Permission::createOrGet('posts', 'create', 'Create posts');
$updatePosts = Permission::createOrGet('posts', 'update', 'Update posts');
$deletePosts = Permission::createOrGet('posts', 'delete', 'Delete posts');
```

### 4. Assign Permissions to Roles

```php
// Admin gets all permissions
$admin->givePermissionTo($viewPosts);
$admin->givePermissionTo($createPosts);
$admin->givePermissionTo($updatePosts);
$admin->givePermissionTo($deletePosts);

// User gets limited permissions
$user->givePermissionTo($viewPosts);
$user->givePermissionTo($createPosts);
```

### 5. Assign Roles to Users

```php
$userModel = User::find(1);
$userModel->assignRole('admin');

// Or assign multiple roles
$userModel->syncRoles(['admin', 'manager']);
```

---

## Usage Examples

### Basic Permission Checks

```php
// Check if user has a role
if ($user->hasRole('admin')) {
    // User is admin
}

// Check if user has any of the given roles
if ($user->hasAnyRole(['admin', 'manager'])) {
    // User is admin or manager
}

// Check if user has all given roles
if ($user->hasAllRoles(['admin', 'moderator'])) {
    // User has both roles
}

// Check if user has a permission
if ($user->can('posts.delete')) {
    // User can delete posts
}

// Check if user does NOT have a permission
if ($user->cannot('users.delete')) {
    // User cannot delete users
}
```

### Using Helper Functions

```php
// Global helper functions
if (can('posts.create')) {
    // Current user can create posts
}

if (hasRole('admin')) {
    // Current user is admin
}

if (hasPermission('posts.delete')) {
    // Current user can delete posts
}
```

### Protecting Routes with Middleware

```php
use Conduit\Routing\Router;

$router = new Router($container);

// Single permission
$router->get('/admin', 'AdminController@index')
    ->middleware('permission:admin.view');

// Multiple permissions (OR logic)
$router->get('/dashboard', 'DashboardController@index')
    ->middleware('permission:admin.view|manager.view');

// Multiple permissions (AND logic)
$router->post('/sensitive', 'SensitiveController@store')
    ->middleware('permission:sensitive.create&sensitive.approve');
```

### Record-Level Authorization (Ownership)

```php
use Conduit\Authorization\Models\PermissionPolicy;

// Users can only update their own posts
$permission = Permission::findByName('posts.update');
PermissionPolicy::createOwnershipPolicy($permission->id, 'user_id');

// Now check authorization with resource
$post = Post::find(1);

if (authorize('posts.update', $post)) {
    // User can update this specific post (owns it)
}
```

### Team-Based Authorization

```php
use Conduit\Authorization\Models\PermissionPolicy;

// Users can only view documents in their team
$permission = Permission::findByName('documents.view');
PermissionPolicy::createTeamPolicy($permission->id, 'team_id');

$document = Document::find(1);

if (authorize('documents.view', $document)) {
    // User can view this document (same team)
}
```

### Custom Policy Rules

```php
use Conduit\Authorization\Models\PermissionPolicy;

$permission = Permission::findByName('offers.view');

// Custom policy: Only view published offers or offers expiring after now
PermissionPolicy::createCustomPolicy($permission->id, [
    'or' => [
        [
            'field' => 'status',
            'operator' => 'equals',
            'value' => 'published',
        ],
        [
            'field' => 'expires_at',
            'operator' => 'greater_than',
            'value' => '{now}',
        ],
    ],
], 50);
```

### Field-Level Restrictions

```php
use Conduit\Authorization\Models\Permission;
use Conduit\Authorization\FieldRestrictor;

// Hide sensitive fields
$permission = Permission::findByName('users.view');
$permission->addFieldRestriction('password', 'hidden');
$permission->addFieldRestriction('ssn', 'hidden');

// Mask fields
$permission->addFieldRestriction('email', 'masked', '***@***');
$permission->addFieldRestriction('credit_card', 'masked', '####'); // Show last 4

// Apply restrictions
$restrictor = new FieldRestrictor($user);
$sanitized = $restrictor->applyRestrictions('users.view', $userModel);

// Result: password and ssn are removed, email is masked
```

### Automatic Query Scoping

```php
use Conduit\Authorization\Traits\AppliesPermissionScopes;

class Post extends Model
{
    use AppliesPermissionScopes;

    protected string $permissionResource = 'posts';
}

// Automatically filters posts based on user's permissions
$posts = Post::forUser($user)->get();

// Only returns posts user can view based on policies
```

### Using PolicyEngine Directly

```php
use Conduit\Authorization\PolicyEngine;

$engine = new PolicyEngine($user);

// Check single permission
if ($engine->authorize('posts.delete', $post)) {
    // User can delete this post
}

// Check multiple permissions (AND)
if ($engine->authorizeAll(['posts.update', 'posts.publish'], $post)) {
    // User has all permissions
}

// Check multiple permissions (OR)
if ($engine->authorizeAny(['posts.update', 'posts.delete'], $post)) {
    // User has at least one permission
}
```

---

## Advanced Features

### Dynamic Values in Policies

Policies support dynamic placeholders:

- `{auth.id}` - Current user's ID
- `{auth.team_id}` - Current user's team ID
- `{auth.department_id}` - Current user's department ID
- `{auth.{field}}` - Any field from current user
- `{now}` - Current timestamp
- `{today}` - Today's date (midnight)

Example:

```php
PermissionPolicy::createCustomPolicy($permission->id, [
    'field' => 'user_id',
    'operator' => 'equals',
    'value' => '{auth.id}', // Dynamic: current user's ID
]);
```

### Supported Operators

Policy conditions support these operators:

- `equals`, `=`, `==` - Equality
- `strict_equals`, `===` - Strict equality
- `not_equals`, `!=`, `<>` - Inequality
- `greater_than`, `>` - Greater than
- `greater_than_or_equal`, `>=` - Greater than or equal
- `less_than`, `<` - Less than
- `less_than_or_equal`, `<=` - Less than or equal
- `in` - Value in array
- `not_in` - Value not in array
- `contains` - String contains
- `starts_with` - String starts with
- `ends_with` - String ends with
- `is_null` - Value is null
- `is_not_null` - Value is not null

### Compound Conditions

```php
// AND logic (all conditions must pass)
PermissionPolicy::createCustomPolicy($permission->id, [
    'and' => [
        ['field' => 'status', 'operator' => 'equals', 'value' => 'published'],
        ['field' => 'user_id', 'operator' => 'equals', 'value' => '{auth.id}'],
    ],
]);

// OR logic (any condition can pass)
PermissionPolicy::createCustomPolicy($permission->id, [
    'or' => [
        ['field' => 'user_id', 'operator' => 'equals', 'value' => '{auth.id}'],
        ['field' => 'is_public', 'operator' => 'equals', 'value' => true],
    ],
]);

// Nested conditions
PermissionPolicy::createCustomPolicy($permission->id, [
    'or' => [
        [
            'and' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'draft'],
                ['field' => 'user_id', 'operator' => 'equals', 'value' => '{auth.id}'],
            ],
        ],
        [
            'field' => 'status',
            'operator' => 'equals',
            'value' => 'published',
        ],
    ],
]);
```

### Field Masking Patterns

```php
// Simple replacement
$permission->addFieldRestriction('password', 'masked', '***');
// Result: '***'

// Show last N characters
$permission->addFieldRestriction('credit_card', 'masked', '####');
// Input: '1234567890123456'
// Result: '************3456'

$permission->addFieldRestriction('ssn', 'masked', '##-####');
// Input: '123-45-6789'
// Result: '***-**-6789'
```

### Readonly Fields During Updates

```php
$permission = Permission::findByName('users.update');
$permission->addFieldRestriction('id', 'readonly');
$permission->addFieldRestriction('created_at', 'readonly');

$restrictor = new FieldRestrictor($user);

// Filter out readonly fields before update
$inputData = [
    'id' => 999, // Attempt to change ID
    'name' => 'Updated Name',
    'created_at' => time(), // Attempt to change timestamp
];

$filtered = $restrictor->filterReadonlyFields('users.update', $inputData);
// Result: ['name' => 'Updated Name']
```

---

## API Reference

### HasRoles Trait Methods

```php
// Role management
$user->assignRole($role);
$user->removeRole($role);
$user->syncRoles([$role1, $role2]);

// Role checking
$user->hasRole($role);
$user->hasAnyRole([$role1, $role2]);
$user->hasAllRoles([$role1, $role2]);

// Permission checking
$user->can($permission);
$user->cannot($permission);
$user->hasPermissionTo($permission);

// Get data
$user->getAllPermissions();
$user->getPermissionNames();
$user->getRoleNames();
```

### Role Model Methods

```php
$role->givePermissionTo($permission);
$role->revokePermissionTo($permission);
$role->syncPermissions([$permission1, $permission2]);
$role->hasPermissionTo($permission);
$role->getPermissionNames();

Role::findBySlug($slug);
Role::findByName($name);
```

### Permission Model Methods

```php
Permission::createOrGet($resource, $action, $description);
Permission::findByName($name);
Permission::findByResource($resource);

$permission->addPolicy($policyType, $conditions, $priority);
$permission->addFieldRestriction($fieldName, $restrictionType, $maskPattern);
$permission->assignToRole($role);
$permission->removeFromRole($role);
$permission->hasPolicies();
$permission->hasFieldRestrictions();
```

### Helper Functions

```php
authorize($permission, $resource, $user);
can($permission, $resource, $user);
cannot($permission, $resource, $user);
hasRole($role, $user);
hasPermission($permission, $user);
applyFieldRestrictions($permission, $model, $user);
roleExists($slug);
permissionExists($name);
createRole($name, $slug, $description);
createPermission($resource, $action, $description);
```

---

## Complete Example

```php
<?php

use App\Models\User;
use App\Models\Post;
use Conduit\Authorization\Models\Role;
use Conduit\Authorization\Models\Permission;
use Conduit\Authorization\Models\PermissionPolicy;

// 1. Create roles
$admin = Role::create(['name' => 'Admin', 'slug' => 'admin']);
$editor = Role::create(['name' => 'Editor', 'slug' => 'editor']);
$author = Role::create(['name' => 'Author', 'slug' => 'author']);

// 2. Create permissions
$viewPosts = Permission::createOrGet('posts', 'view');
$createPosts = Permission::createOrGet('posts', 'create');
$updatePosts = Permission::createOrGet('posts', 'update');
$deletePosts = Permission::createOrGet('posts', 'delete');

// 3. Assign permissions to roles
$admin->givePermissionTo([$viewPosts, $createPosts, $updatePosts, $deletePosts]);
$editor->givePermissionTo([$viewPosts, $createPosts, $updatePosts]);
$author->givePermissionTo([$viewPosts, $createPosts]);

// 4. Add ownership policy (authors can only update their own posts)
PermissionPolicy::createOwnershipPolicy($updatePosts->id, 'user_id', 100);

// 5. Add field restrictions
$updatePosts->addFieldRestriction('view_count', 'readonly');
$updatePosts->addFieldRestriction('created_at', 'readonly');

// 6. Assign role to user
$user = User::find(1);
$user->assignRole('author');

// 7. Check permissions
if ($user->can('posts.create')) {
    $post = Post::create([
        'title' => 'My Post',
        'content' => 'Post content',
        'user_id' => $user->id,
    ]);
}

// 8. Check record-level permission
if (authorize('posts.update', $post)) {
    $post->update(['title' => 'Updated Title']);
}

// 9. Protect routes
$router->put('/posts/{id}', 'PostController@update')
    ->middleware('permission:posts.update');
```

---

## Best Practices

1. **Use descriptive permission names**: `posts.view`, `users.export`, `reports.generate`
2. **Keep policies simple**: Complex logic should be in application code
3. **Cache aggressively**: Permission checks can be expensive
4. **Use ownership policies**: Most common use case for record-level auth
5. **Document custom policies**: Complex JSON rules need documentation
6. **Test thoroughly**: Write tests for all permission scenarios
7. **Seed initial data**: Always provide default roles/permissions

---

## Troubleshooting

### Permission denied despite having role

- Check if permission is assigned to role
- Check if user has the role assigned
- Clear permission cache: `$user->clearPermissionCache()`

### Policy not working

- Verify policy is attached to permission
- Check policy priority (higher = checked first)
- Verify resource has required fields
- Test with PolicyEngine directly for debugging

### Field restrictions not applied

- Ensure user has the permission
- Check restriction type (hidden/masked/readonly)
- Verify field name matches exactly

---

For more information, see the source code in `src/Authorization/`.
