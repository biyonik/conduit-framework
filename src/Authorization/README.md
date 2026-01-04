# Conduit Authorization (RBAC) Package

A comprehensive, production-ready RBAC (Role-Based Access Control) system for the Conduit PHP Framework.

## Features

### ✅ Multi-Level Authorization

- **Table-Level**: Control access to entire resources
- **Record-Level**: Dynamic policies (ownership, team, department, custom)
- **Action-Level**: Fine-grained operations (view, create, update, delete, export, etc.)
- **Field-Level**: Hide, mask, or make fields read-only

### ✅ Dynamic Policy Engine

- Ownership-based policies
- Team/Department-based policies
- Custom JSON-based rules
- Compound conditions (AND/OR logic)
- Dynamic value resolution (`{auth.id}`, `{now}`, etc.)
- Priority-based policy evaluation

### ✅ Field-Level Security

- **Hidden**: Fields completely removed from output
- **Masked**: Values masked with patterns (e.g., `****1234`)
- **Readonly**: Fields viewable but not modifiable

### ✅ Query Scoping

- Automatic filtering of query results
- Applies policies at database level
- Prevents unauthorized data access

### ✅ Production-Ready

- Comprehensive test coverage
- Security-first design
- Performance optimized
- Well-documented

## Quick Start

### 1. Install

```bash
# Run migrations
php conduit migrate

# Seed initial data
php conduit db:seed RBACSeeder
```

### 2. Add to User Model

```php
use Conduit\Authorization\Traits\HasRoles;

class User extends Model
{
    use HasRoles;
}
```

### 3. Use in Code

```php
// Check permissions
if ($user->can('posts.delete')) {
    $post->delete();
}

// Protect routes
$router->delete('/posts/{id}', 'PostController@destroy')
    ->middleware('permission:posts.delete');

// Record-level authorization
if (authorize('posts.update', $post)) {
    // User can update THIS specific post
}
```

## Architecture

```
Authorization/
├── Models/
│   ├── Role.php                    # Role model
│   ├── Permission.php              # Permission model
│   ├── PermissionPolicy.php        # Policy model
│   └── FieldRestriction.php        # Field restriction model
├── Traits/
│   ├── HasRoles.php                # Add to User model
│   └── AppliesPermissionScopes.php # Add to resource models
├── PolicyEngine.php                # Core authorization engine
├── FieldRestrictor.php             # Field-level security
├── Middleware/
│   └── CheckPermission.php         # Route protection
├── AuthorizationServiceProvider.php
└── helpers.php                     # Global functions
```

## Components

### Models

**Role**: Groups of permissions
- Create, assign, sync permissions
- Find by slug or name
- Get permission names

**Permission**: Actions on resources
- Format: `resource.action` (e.g., `posts.view`)
- Attach policies and field restrictions
- Assign to roles

**PermissionPolicy**: Dynamic authorization rules
- Types: ownership, team, department, custom
- JSON-based conditions
- Priority-based evaluation

**FieldRestriction**: Field-level security
- Types: hidden, masked, readonly
- Mask patterns for sensitive data
- Applies during read/write operations

### Traits

**HasRoles**: Add to User model
```php
$user->assignRole('admin');
$user->can('posts.delete');
$user->getAllPermissions();
```

**AppliesPermissionScopes**: Add to resource models
```php
Post::forUser($user)->get(); // Auto-filtered by policies
```

### Core Classes

**PolicyEngine**: Authorization logic
```php
$engine = new PolicyEngine($user);
$engine->authorize('posts.delete', $post);
```

**FieldRestrictor**: Field security
```php
$restrictor = new FieldRestrictor($user);
$sanitized = $restrictor->applyRestrictions('users.view', $user);
```

**CheckPermission**: Middleware
```php
->middleware('permission:admin.view|super.admin')
```

## Database Schema

```sql
roles                   # User roles
├── id
├── name
├── slug
└── description

permissions             # Available permissions
├── id
├── resource           # e.g., 'posts'
├── action             # e.g., 'view', 'create'
├── name               # e.g., 'posts.view'
└── description

permission_policies     # Dynamic rules
├── id
├── permission_id
├── policy_type        # ownership, team, department, custom
├── conditions         # JSON
└── priority

field_restrictions      # Field-level security
├── id
├── permission_id
├── field_name
├── restriction_type   # hidden, masked, readonly
└── mask_pattern

role_user              # Pivot: user ↔ role
permission_role        # Pivot: role ↔ permission
```

## Examples

See `examples/RBAC_USAGE.md` for comprehensive examples including:
- Basic setup
- Permission checks
- Route protection
- Record-level auth
- Field restrictions
- Custom policies
- Complete working example

## Testing

Run tests:
```bash
vendor/bin/phpunit tests/Unit/Authorization/
```

Tests cover:
- Role management
- Permission assignment
- Policy evaluation
- Field restrictions
- Integration scenarios

## Performance

### Optimizations Included

- Permission caching on user model
- Lazy loading of policies
- Indexed database queries
- Minimal N+1 queries

### Best Practices

1. Cache permission checks when possible
2. Use query scopes instead of post-filtering
3. Limit policy complexity
4. Index foreign keys and lookups
5. Clear cache after role/permission changes

## Security

### Built-in Protections

- SQL injection prevention (validated identifiers)
- Mass assignment protection
- Field-level masking for sensitive data
- Readonly fields prevent tampering
- Policy-based row-level security

### Security Checklist

- ✅ All user input validated
- ✅ Prepared statements for queries
- ✅ Column/table names validated
- ✅ Permission checks on all operations
- ✅ Field restrictions enforce data privacy
- ✅ Policies prevent unauthorized access

## Documentation

- **Full Guide**: `examples/RBAC_USAGE.md`
- **API Reference**: See class docblocks
- **Examples**: `examples/` directory
- **Tests**: `tests/Unit/Authorization/`

## License

Part of the Conduit Framework. See LICENSE file.

## Support

For issues, questions, or contributions, please see the main Conduit Framework repository.
