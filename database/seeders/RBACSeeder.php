<?php

declare(strict_types=1);

use Conduit\Authorization\Models\Role;
use Conduit\Authorization\Models\Permission;
use Conduit\Authorization\Models\PermissionPolicy;

/**
 * RBAC Seeder
 *
 * Seeds initial roles and permissions for the application.
 *
 * This is a sample seeder - customize it based on your application's needs.
 *
 * Default roles created:
 * - super-admin: Full system access
 * - admin: Administrative access
 * - manager: Management access
 * - user: Basic user access
 *
 * Default permissions for common resources:
 * - users: view, create, update, delete, export
 * - posts: view, create, update, delete, export
 * - reports: view, create, export
 */
class RBACSeeder
{
    /**
     * Run the seeder
     *
     * @return void
     */
    public function run(): void
    {
        echo "Seeding RBAC roles and permissions...\n";

        // Create roles
        $superAdmin = $this->createRole('Super Admin', 'super-admin', 'Full system access');
        $admin = $this->createRole('Admin', 'admin', 'Administrative access');
        $manager = $this->createRole('Manager', 'manager', 'Management access');
        $user = $this->createRole('User', 'user', 'Basic user access');

        echo "  ✓ Created 4 roles\n";

        // Create permissions for users resource
        $usersPermissions = $this->createResourcePermissions('users', [
            'view' => 'View users',
            'create' => 'Create new users',
            'update' => 'Update existing users',
            'delete' => 'Delete users',
            'export' => 'Export user data',
        ]);

        // Create permissions for posts resource
        $postsPermissions = $this->createResourcePermissions('posts', [
            'view' => 'View posts',
            'create' => 'Create new posts',
            'update' => 'Update existing posts',
            'delete' => 'Delete posts',
            'export' => 'Export post data',
        ]);

        // Create permissions for reports resource
        $reportsPermissions = $this->createResourcePermissions('reports', [
            'view' => 'View reports',
            'create' => 'Create reports',
            'export' => 'Export reports',
        ]);

        echo "  ✓ Created 13 permissions\n";

        // Assign permissions to super-admin (all permissions)
        foreach (array_merge($usersPermissions, $postsPermissions, $reportsPermissions) as $permission) {
            $superAdmin->givePermissionTo($permission);
        }

        // Assign permissions to admin
        foreach (array_merge($usersPermissions, $postsPermissions, $reportsPermissions) as $permission) {
            $admin->givePermissionTo($permission);
        }

        // Assign permissions to manager
        $manager->givePermissionTo($usersPermissions['view']);
        $manager->givePermissionTo($postsPermissions['view']);
        $manager->givePermissionTo($postsPermissions['create']);
        $manager->givePermissionTo($postsPermissions['update']);
        $manager->givePermissionTo($reportsPermissions['view']);
        $manager->givePermissionTo($reportsPermissions['export']);

        // Assign permissions to user
        $user->givePermissionTo($postsPermissions['view']);
        $user->givePermissionTo($postsPermissions['create']);

        echo "  ✓ Assigned permissions to roles\n";

        // Add ownership policies for posts
        // Users can update/delete their own posts
        $this->addOwnershipPolicy($postsPermissions['update'], 'user_id');
        $this->addOwnershipPolicy($postsPermissions['delete'], 'user_id');

        echo "  ✓ Added ownership policies\n";

        // Add field restrictions
        // Hide sensitive user fields from regular users
        $usersPermissions['view']->addFieldRestriction('password', 'hidden');
        $usersPermissions['view']->addFieldRestriction('email', 'masked', '***@***');
        $usersPermissions['view']->addFieldRestriction('phone', 'masked', '***-****');

        echo "  ✓ Added field restrictions\n";

        echo "RBAC seeding completed successfully!\n";
    }

    /**
     * Create a role
     *
     * @param string $name
     * @param string $slug
     * @param string $description
     * @return Role
     */
    protected function createRole(string $name, string $slug, string $description): Role
    {
        $existing = Role::findBySlug($slug);
        if ($existing) {
            return $existing;
        }

        return Role::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
        ]);
    }

    /**
     * Create permissions for a resource
     *
     * @param string $resource Resource name
     * @param array $actions Associative array of action => description
     * @return array Array of Permission instances
     */
    protected function createResourcePermissions(string $resource, array $actions): array
    {
        $permissions = [];

        foreach ($actions as $action => $description) {
            $permissions[$action] = Permission::createOrGet($resource, $action, $description);
        }

        return $permissions;
    }

    /**
     * Add ownership policy to a permission
     *
     * @param Permission $permission
     * @param string $ownerField
     * @return void
     */
    protected function addOwnershipPolicy(Permission $permission, string $ownerField = 'user_id'): void
    {
        // Check if policy already exists
        $existing = $permission->policies()
            ->where('policy_type', '=', 'ownership')
            ->first();

        if ($existing) {
            return;
        }

        PermissionPolicy::createOwnershipPolicy(
            $permission->getKey(),
            $ownerField,
            100 // High priority
        );
    }
}
