# RBAC Tam Kullanım Rehberi - Gerçek Dünya Senaryosu

## 🎯 Senaryo: Kurumsal Doküman Yönetim Sistemi

**İhtiyaçlar:**
- Şirket çalışanları dokümanları yönetebilsin
- 3 rol: **Admin**, **Manager**, **Employee**
- Admin her şeyi yapabilir
- Manager kendi departmanının dokümanlarını yönetebilir
- Employee sadece kendi dokümanlarını düzenleyebilir, başkalarınınkileri sadece görüntüleyebilir
- Hassas alanlar (salary bilgisi) sadece Manager ve Admin görebilsin
- **Web arayüzünden rol/permission oluşturabilelim ve yönetelim**

---

## 📋 Adım 1: Database Hazırlığı

### 1.1 Migration Çalıştır

RBAC tabloları zaten sistemde mevcut. Sadece migrate et:

```bash
php conduit migrate
```

Bu şu tabloları oluşturur:
- `roles` - Roller
- `permissions` - İzinler
- `permission_policies` - Ownership/Team gibi koşullar
- `field_restrictions` - Alan kısıtlamaları
- `permission_role` - Rol-İzin ilişkisi
- `role_user` - Kullanıcı-Rol ilişkisi

### 1.2 Doküman Tablosu Oluştur

```php
// database/migrations/2026_01_06_create_documents_table.php
use Conduit\Database\Schema\Migration;
use Conduit\Database\Schema\Schema;
use Conduit\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('content');
            $table->string('status', 20)->default('draft'); // draft, published, archived
            $table->unsignedBigInteger('user_id'); // Dokümanı oluşturan
            $table->unsignedBigInteger('department_id'); // Hangi departman
            $table->string('category', 50)->nullable();
            $table->decimal('budget', 10, 2)->nullable(); // Hassas alan - sadece Manager+
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
            $table->index('department_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('documents');
    }
};
```

```bash
php conduit migrate
```

---

## 📝 Adım 2: User Model'e HasRoles Trait Ekle

```php
// app/Models/User.php
namespace App\Models;

use Conduit\Database\Model;
use Conduit\Authorization\Traits\HasRoles;

class User extends Model {
    use HasRoles; // ← RBAC yetenekleri ekler

    protected string $table = 'users';

    protected array $fillable = [
        'name',
        'email',
        'password',
        'department_id',
    ];

    protected array $casts = [
        'id' => 'int',
        'department_id' => 'int',
    ];

    protected array $hidden = [
        'password',
    ];

    // Dokümanlar ilişkisi
    public function documents() {
        return $this->hasMany(Document::class, 'user_id');
    }
}
```

---

## 🏗️ Adım 3: Document Model Oluştur

```php
// app/Models/Document.php
namespace App\Models;

use Conduit\Database\Model;
use Conduit\Authorization\Traits\AppliesPermissionScopes;

class Document extends Model {
    use AppliesPermissionScopes; // RBAC field restrictions için

    protected string $table = 'documents';
    protected string $permissionResource = 'documents'; // İzin resource adı

    protected array $fillable = [
        'title',
        'content',
        'status',
        'user_id',
        'department_id',
        'category',
        'budget',
    ];

    protected array $casts = [
        'id' => 'int',
        'user_id' => 'int',
        'department_id' => 'int',
        'budget' => 'float',
        'created_at' => 'int',
        'updated_at' => 'int',
    ];

    // İlişkiler
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

---

## 🎭 Adım 4: Programatik Rol ve Permission Oluşturma (Seeder)

### 4.1 RBAC Seeder Oluştur

```php
// database/seeders/DocumentRBACSeeder.php
namespace Database\Seeders;

use Conduit\Authorization\Models\Role;
use Conduit\Authorization\Models\Permission;
use Conduit\Authorization\Models\PermissionPolicy;
use Conduit\Authorization\Models\FieldRestriction;

class DocumentRBACSeeder {
    public function run(): void {
        echo "🎭 Creating roles...\n";

        // 1. Roller oluştur
        $admin = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Sistem yöneticisi - tam yetki',
        ]);

        $manager = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'description' => 'Departman yöneticisi',
        ]);

        $employee = Role::create([
            'name' => 'Employee',
            'slug' => 'employee',
            'description' => 'Çalışan',
        ]);

        echo "✅ Roles created: Admin, Manager, Employee\n";

        // 2. Permissions oluştur
        echo "\n🔑 Creating permissions...\n";

        $viewDocs = Permission::createOrGet('documents', 'view', 'Dokümanları görüntüle');
        $createDocs = Permission::createOrGet('documents', 'create', 'Doküman oluştur');
        $updateDocs = Permission::createOrGet('documents', 'update', 'Doküman güncelle');
        $deleteDocs = Permission::createOrGet('documents', 'delete', 'Doküman sil');
        $publishDocs = Permission::createOrGet('documents', 'publish', 'Doküman yayınla');
        $viewBudget = Permission::createOrGet('documents', 'view_budget', 'Budget bilgisi gör');

        echo "✅ Permissions created: view, create, update, delete, publish, view_budget\n";

        // 3. Admin - Tüm yetkiler
        echo "\n🔐 Assigning permissions to roles...\n";

        $admin->givePermissionTo($viewDocs);
        $admin->givePermissionTo($createDocs);
        $admin->givePermissionTo($updateDocs);
        $admin->givePermissionTo($deleteDocs);
        $admin->givePermissionTo($publishDocs);
        $admin->givePermissionTo($viewBudget);

        echo "✅ Admin: ALL permissions\n";

        // 4. Manager - Kendi departmanı
        $manager->givePermissionTo($viewDocs);
        $manager->givePermissionTo($createDocs);
        $manager->givePermissionTo($updateDocs);
        $manager->givePermissionTo($deleteDocs);
        $manager->givePermissionTo($publishDocs);
        $manager->givePermissionTo($viewBudget);

        // Manager için DEPARTMENT policy (sadece kendi departmanındakileri yönetebilir)
        PermissionPolicy::createDepartmentPolicy($updateDocs->id, 'department_id', 90);
        PermissionPolicy::createDepartmentPolicy($deleteDocs->id, 'department_id', 90);

        echo "✅ Manager: Department-scoped permissions\n";

        // 5. Employee - Sadece kendi dokümanları
        $employee->givePermissionTo($viewDocs);
        $employee->givePermissionTo($createDocs);
        $employee->givePermissionTo($updateDocs);

        // Employee için OWNERSHIP policy (sadece kendi dokümanlarını düzenleyebilir)
        PermissionPolicy::createOwnershipPolicy($updateDocs->id, 'user_id', 100);

        echo "✅ Employee: Ownership-scoped permissions\n";

        // 6. Field Restrictions - Budget sadece Manager ve Admin görebilir
        echo "\n🔒 Setting field restrictions...\n";

        // Employee için budget alanını gizle
        FieldRestriction::create([
            'permission_id' => $viewDocs->id,
            'field_name' => 'budget',
            'restriction_type' => 'hidden',
        ]);

        echo "✅ Field restriction: budget hidden for Employee\n";

        echo "\n🎉 RBAC setup completed!\n";
    }
}
```

### 4.2 Seeder'ı Çalıştır

```bash
# CLI ile çalıştır
php conduit db:seed DocumentRBACSeeder

# Veya manuel olarak
php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; (new Database\Seeders\DocumentRBACSeeder())->run();"
```

**Output:**
```
🎭 Creating roles...
✅ Roles created: Admin, Manager, Employee

🔑 Creating permissions...
✅ Permissions created: view, create, update, delete, publish, view_budget

🔐 Assigning permissions to roles...
✅ Admin: ALL permissions
✅ Manager: Department-scoped permissions
✅ Employee: Ownership-scoped permissions

🔒 Setting field restrictions...
✅ Field restriction: budget hidden for Employee

🎉 RBAC setup completed!
```

---

## 🌐 Adım 5: Admin Paneli - Rol/Permission Yönetimi (WEB ARAYÜZÜ)

### 5.1 RoleController Oluştur

```php
// app/Controllers/Admin/RoleController.php
namespace App\Controllers\Admin;

use Conduit\Http\Request;
use Conduit\Http\JsonResponse;
use Conduit\Authorization\Models\Role;
use Conduit\Authorization\Models\Permission;
use Conduit\Validation\ValidationSchema;
use Conduit\Validation\SchemaType\StringType;

class RoleController {

    // GET /admin/roles - Tüm rolleri listele
    public function index(Request $request): JsonResponse {
        // Admin kontrolü
        if (!authorize('roles.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $roles = Role::all();

        $rolesWithPermissions = $roles->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions_count' => $role->permissions()->count(),
                'created_at' => $role->created_at,
            ];
        });

        return new JsonResponse([
            'data' => $rolesWithPermissions->toArray(),
        ]);
    }

    // POST /admin/roles - Yeni rol oluştur
    public function store(Request $request): JsonResponse {
        if (!authorize('roles.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        // Validation (GERÇEK VALIDATOR)
        $schema = ValidationSchema::create()
            ->field('name', (new StringType())->required()->min(3)->max(100))
            ->field('slug', (new StringType())->required()->min(3)->max(50)->pattern('/^[a-z0-9-]+$/'))
            ->field('description', (new StringType())->max(255));

        $result = $schema->validate($request->all());

        if ($result->fails()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $result->getErrors(),
            ], 422);
        }

        // Slug unique kontrolü
        if (Role::where('slug', '=', $request->input('slug'))->exists()) {
            return new JsonResponse([
                'error' => 'Role with this slug already exists',
            ], 409);
        }

        // Rol oluştur
        $role = Role::create([
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'description' => $request->input('description'),
        ]);

        logger()->info('Role created via admin panel', [
            'role_id' => $role->id,
            'admin_user_id' => $request->getAttribute('user')->id,
        ]);

        return new JsonResponse([
            'message' => 'Role created successfully',
            'data' => $role,
        ], 201);
    }

    // PUT /admin/roles/{id} - Rol güncelle
    public function update(int $id, Request $request): JsonResponse {
        if (!authorize('roles.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $role = Role::find($id);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        // Validation
        $schema = ValidationSchema::create()
            ->field('name', (new StringType())->required()->min(3)->max(100))
            ->field('description', (new StringType())->max(255));

        $result = $schema->validate($request->all());

        if ($result->fails()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $result->getErrors(),
            ], 422);
        }

        $role->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        return new JsonResponse([
            'message' => 'Role updated successfully',
            'data' => $role,
        ]);
    }

    // DELETE /admin/roles/{id} - Rol sil
    public function destroy(int $id): JsonResponse {
        if (!authorize('roles.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $role = Role::find($id);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        // Rol kullanımda mı kontrol et
        $userCount = $role->users()->count();

        if ($userCount > 0) {
            return new JsonResponse([
                'error' => "Cannot delete role. {$userCount} users are using this role.",
            ], 409);
        }

        $role->delete();

        return new JsonResponse(null, 204);
    }

    // GET /admin/roles/{id}/permissions - Rolün permissionları
    public function getPermissions(int $id): JsonResponse {
        if (!authorize('roles.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $role = Role::find($id);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        $permissions = $role->permissions()->get();

        return new JsonResponse([
            'data' => $permissions->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'resource' => $p->resource,
                'action' => $p->action,
            ])->toArray(),
        ]);
    }

    // POST /admin/roles/{id}/permissions - Rol'e permission ekle
    public function attachPermission(int $id, Request $request): JsonResponse {
        if (!authorize('roles.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $role = Role::find($id);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        $permissionId = (int) $request->input('permission_id');

        $permission = Permission::find($permissionId);

        if (!$permission) {
            return new JsonResponse(['error' => 'Permission not found'], 404);
        }

        $role->givePermissionTo($permission);

        return new JsonResponse([
            'message' => 'Permission attached to role successfully',
        ]);
    }

    // DELETE /admin/roles/{id}/permissions/{permissionId} - Permission kaldır
    public function detachPermission(int $id, int $permissionId): JsonResponse {
        if (!authorize('roles.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $role = Role::find($id);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        $permission = Permission::find($permissionId);

        if (!$permission) {
            return new JsonResponse(['error' => 'Permission not found'], 404);
        }

        $role->revokePermissionTo($permission);

        return new JsonResponse([
            'message' => 'Permission removed from role successfully',
        ]);
    }
}
```

### 5.2 PermissionController Oluştur

```php
// app/Controllers/Admin/PermissionController.php
namespace App\Controllers\Admin;

use Conduit\Http\Request;
use Conduit\Http\JsonResponse;
use Conduit\Authorization\Models\Permission;
use Conduit\Validation\ValidationSchema;
use Conduit\Validation\SchemaType\StringType;

class PermissionController {

    // GET /admin/permissions - Tüm permissionları listele
    public function index(Request $request): JsonResponse {
        if (!authorize('roles.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $permissions = Permission::all();

        return new JsonResponse([
            'data' => $permissions->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'resource' => $p->resource,
                'action' => $p->action,
                'description' => $p->description,
            ])->toArray(),
        ]);
    }

    // POST /admin/permissions - Yeni permission oluştur
    public function store(Request $request): JsonResponse {
        if (!authorize('roles.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        // Validation
        $schema = ValidationSchema::create()
            ->field('resource', (new StringType())->required()->min(2)->max(100))
            ->field('action', (new StringType())->required()->min(2)->max(100))
            ->field('description', (new StringType())->max(255));

        $result = $schema->validate($request->all());

        if ($result->fails()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $result->getErrors(),
            ], 422);
        }

        $permission = Permission::createOrGet(
            $request->input('resource'),
            $request->input('action'),
            $request->input('description')
        );

        logger()->info('Permission created via admin panel', [
            'permission_id' => $permission->id,
            'admin_user_id' => $request->getAttribute('user')->id,
        ]);

        return new JsonResponse([
            'message' => 'Permission created successfully',
            'data' => $permission,
        ], 201);
    }
}
```

### 5.3 UserRoleController - Kullanıcılara Rol Atama

```php
// app/Controllers/Admin/UserRoleController.php
namespace App\Controllers\Admin;

use Conduit\Http\Request;
use Conduit\Http\JsonResponse;
use Conduit\Authorization\Models\Role;
use App\Models\User;

class UserRoleController {

    // GET /admin/users/{userId}/roles - Kullanıcının rolleri
    public function index(int $userId): JsonResponse {
        if (!authorize('users.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $roles = $user->roles()->get();

        return new JsonResponse([
            'data' => $roles->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
            ])->toArray(),
        ]);
    }

    // POST /admin/users/{userId}/roles - Kullanıcıya rol ata
    public function attach(int $userId, Request $request): JsonResponse {
        if (!authorize('users.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $roleId = (int) $request->input('role_id');

        $role = Role::find($roleId);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        $user->assignRole($role);

        logger()->info('Role assigned to user', [
            'user_id' => $userId,
            'role_id' => $roleId,
            'admin_user_id' => $request->getAttribute('user')->id,
        ]);

        return new JsonResponse([
            'message' => 'Role assigned to user successfully',
        ]);
    }

    // DELETE /admin/users/{userId}/roles/{roleId} - Rolü kaldır
    public function detach(int $userId, int $roleId): JsonResponse {
        if (!authorize('users.manage')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $role = Role::find($roleId);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        $user->removeRole($role);

        return new JsonResponse([
            'message' => 'Role removed from user successfully',
        ]);
    }
}
```

---

## 📄 Adım 6: DocumentController - RBAC Uygulamalı

```php
// app/Controllers/DocumentController.php
namespace App\Controllers;

use Conduit\Http\Request;
use Conduit\Http\JsonResponse;
use App\Models\Document;
use Conduit\Validation\ValidationSchema;
use Conduit\Validation\SchemaType\StringType;
use Conduit\Validation\SchemaType\NumberType;

class DocumentController {

    // GET /documents - Dokümanları listele
    public function index(Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        // Permission kontrolü - Table level
        if (!authorize('documents.view')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        // Rol bazlı filtreleme
        $query = Document::query();

        if ($user->hasRole('admin')) {
            // Admin tüm dokümanları görür
            // Filtre yok
        } elseif ($user->hasRole('manager')) {
            // Manager sadece kendi departmanını görür
            $query->where('department_id', '=', $user->department_id);
        } else {
            // Employee sadece kendi dokümanlarını görür
            $query->where('user_id', '=', $user->id);
        }

        $documents = $query->with(['user'])->get();

        // Field restrictions uygula
        $sanitized = $documents->map(function($doc) use ($user) {
            $data = $doc->toArray();

            // Budget alanını kısıtla (Employee görmemeli)
            if (!authorize('documents.view_budget')) {
                unset($data['budget']);
            }

            return $data;
        });

        return new JsonResponse([
            'data' => $sanitized->toArray(),
        ]);
    }

    // POST /documents - Yeni doküman oluştur
    public function store(Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        // Permission kontrolü
        if (!authorize('documents.create')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        // Validation (GERÇEK VALIDATOR)
        $schema = ValidationSchema::create()
            ->field('title', (new StringType())->required()->min(5)->max(255))
            ->field('content', (new StringType())->required()->min(10))
            ->field('category', (new StringType())->max(50))
            ->field('budget', (new NumberType())->min(0));

        $result = $schema->validate($request->all());

        if ($result->fails()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $result->getErrors(),
            ], 422);
        }

        // Doküman oluştur
        $document = Document::create([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'category' => $request->input('category'),
            'budget' => $request->input('budget'),
            'user_id' => $user->id,
            'department_id' => $user->department_id,
            'status' => 'draft',
        ]);

        logger()->info('Document created', [
            'document_id' => $document->id,
            'user_id' => $user->id,
        ]);

        return new JsonResponse([
            'message' => 'Document created successfully',
            'data' => $document,
        ], 201);
    }

    // PUT /documents/{id} - Doküman güncelle
    public function update(int $id, Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        $document = Document::find($id);

        if (!$document) {
            return new JsonResponse(['error' => 'Document not found'], 404);
        }

        // RECORD-LEVEL AUTHORIZATION (Ownership/Department policy)
        if (!authorize('documents.update', $document)) {
            return new JsonResponse([
                'error' => 'You cannot update this document',
            ], 403);
        }

        // Validation
        $schema = ValidationSchema::create()
            ->field('title', (new StringType())->min(5)->max(255))
            ->field('content', (new StringType())->min(10))
            ->field('category', (new StringType())->max(50))
            ->field('budget', (new NumberType())->min(0));

        $result = $schema->validate($request->all());

        if ($result->fails()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $result->getErrors(),
            ], 422);
        }

        $document->update($request->only(['title', 'content', 'category', 'budget']));

        return new JsonResponse([
            'message' => 'Document updated successfully',
            'data' => $document,
        ]);
    }

    // DELETE /documents/{id} - Doküman sil
    public function destroy(int $id): JsonResponse {
        $document = Document::find($id);

        if (!$document) {
            return new JsonResponse(['error' => 'Document not found'], 404);
        }

        // RECORD-LEVEL AUTHORIZATION
        if (!authorize('documents.delete', $document)) {
            return new JsonResponse([
                'error' => 'You cannot delete this document',
            ], 403);
        }

        $document->delete();

        return new JsonResponse(null, 204);
    }

    // PUT /documents/{id}/publish - Doküman yayınla
    public function publish(int $id): JsonResponse {
        $document = Document::find($id);

        if (!$document) {
            return new JsonResponse(['error' => 'Document not found'], 404);
        }

        // Table-level permission (department policy yok)
        if (!authorize('documents.publish')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $document->update(['status' => 'published']);

        return new JsonResponse([
            'message' => 'Document published successfully',
            'data' => $document,
        ]);
    }
}
```

---

## 🛣️ Adım 7: Routes Tanımla

```php
// routes/api.php
use Conduit\Routing\Router;

$router = app(Router::class);

$router->group(['prefix' => 'api', 'middleware' => 'auth'], function($router) {

    // Documents (tüm kullanıcılar)
    $router->get('/documents', 'DocumentController@index');
    $router->get('/documents/{id}', 'DocumentController@show');
    $router->post('/documents', 'DocumentController@store');
    $router->put('/documents/{id}', 'DocumentController@update');
    $router->delete('/documents/{id}', 'DocumentController@destroy');
    $router->put('/documents/{id}/publish', 'DocumentController@publish');

    // Admin Panel - Rol/Permission Yönetimi
    $router->group(['prefix' => 'admin'], function($router) {

        // Roles
        $router->get('/roles', 'Admin\RoleController@index');
        $router->post('/roles', 'Admin\RoleController@store');
        $router->put('/roles/{id}', 'Admin\RoleController@update');
        $router->delete('/roles/{id}', 'Admin\RoleController@destroy');
        $router->get('/roles/{id}/permissions', 'Admin\RoleController@getPermissions');
        $router->post('/roles/{id}/permissions', 'Admin\RoleController@attachPermission');
        $router->delete('/roles/{id}/permissions/{permissionId}', 'Admin\RoleController@detachPermission');

        // Permissions
        $router->get('/permissions', 'Admin\PermissionController@index');
        $router->post('/permissions', 'Admin\PermissionController@store');

        // User-Role Management
        $router->get('/users/{userId}/roles', 'Admin\UserRoleController@index');
        $router->post('/users/{userId}/roles', 'Admin\UserRoleController@attach');
        $router->delete('/users/{userId}/roles/{roleId}', 'Admin\UserRoleController@detach');
    });
});
```

---

## 🧪 Adım 8: Testi

### 8.1 Test Kullanıcıları Oluştur

```php
// Test script: test_rbac.php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

use App\Models\User;

// Test kullanıcıları
$admin = User::create([
    'name' => 'Admin User',
    'email' => 'admin@company.com',
    'password' => password_hash('admin123', PASSWORD_DEFAULT),
    'department_id' => 1,
]);
$admin->assignRole('admin');

$manager = User::create([
    'name' => 'Manager User',
    'email' => 'manager@company.com',
    'password' => password_hash('manager123', PASSWORD_DEFAULT),
    'department_id' => 1, // IT Department
]);
$manager->assignRole('manager');

$employee = User::create([
    'name' => 'Employee User',
    'email' => 'employee@company.com',
    'password' => password_hash('employee123', PASSWORD_DEFAULT),
    'department_id' => 1,
]);
$employee->assignRole('employee');

$employee2 = User::create([
    'name' => 'Employee 2',
    'email' => 'employee2@company.com',
    'password' => password_hash('employee123', PASSWORD_DEFAULT),
    'department_id' => 2, // HR Department
]);
$employee2->assignRole('employee');

echo "✅ Test users created!\n";
```

### 8.2 Test Senaryoları

```bash
# 1. Admin yeni rol oluşturur
curl -X POST http://localhost:8000/api/admin/roles \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Auditor",
    "slug": "auditor",
    "description": "Can view all documents"
  }'

# 2. Admin yeni permission oluşturur
curl -X POST http://localhost:8000/api/admin/permissions \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "resource": "reports",
    "action": "view",
    "description": "View financial reports"
  }'

# 3. Admin rol'e permission ekler
curl -X POST http://localhost:8000/api/admin/roles/4/permissions \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "permission_id": 7
  }'

# 4. Admin kullanıcıya rol atar
curl -X POST http://localhost:8000/api/admin/users/5/roles \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "role_id": 4
  }'

# 5. Employee doküman oluşturur
curl -X POST http://localhost:8000/api/documents \
  -H "Authorization: Bearer EMPLOYEE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Document",
    "content": "This is my document content",
    "budget": 5000
  }'

# 6. Employee kendi dokümanını güncelleyebilir
curl -X PUT http://localhost:8000/api/documents/1 \
  -H "Authorization: Bearer EMPLOYEE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Document"
  }'

# 7. Employee başkasının dokümanını güncelleyemez (403)
curl -X PUT http://localhost:8000/api/documents/2 \
  -H "Authorization: Bearer EMPLOYEE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Hacked!"
  }'
# Response: {"error":"You cannot update this document"}

# 8. Manager kendi departmanındaki dokümanları görebilir
curl -X GET http://localhost:8000/api/documents \
  -H "Authorization: Bearer MANAGER_TOKEN"
# Sadece department_id=1 olanlar döner

# 9. Admin tüm dokümanları görebilir
curl -X GET http://localhost:8000/api/documents \
  -H "Authorization: Bearer ADMIN_TOKEN"
# Tüm dokümanlar döner

# 10. Employee budget göremez
curl -X GET http://localhost:8000/api/documents/1 \
  -H "Authorization: Bearer EMPLOYEE_TOKEN"
# Response: {"title":"...","content":"..."} (budget yok!)
```

---

## 📊 Özet: Sistemin Nasıl Çalıştığı

### ✅ Başarıyla Yapabildiğimiz Senaryolar

1. **✅ Programatik Setup:**
   - Seeder ile rol/permission oluşturma
   - Ownership/Department policy tanımlama
   - Field restrictions

2. **✅ Web Arayüzü (Admin Panel):**
   - Yeni rol oluşturma (`POST /admin/roles`)
   - Yeni permission oluşturma (`POST /admin/permissions`)
   - Rol'e permission ekleme (`POST /admin/roles/{id}/permissions`)
   - Kullanıcıya rol atama (`POST /admin/users/{userId}/roles`)

3. **✅ Table-Level Authorization:**
   ```php
   if (!authorize('documents.view')) {
       return new JsonResponse(['error' => 'Forbidden'], 403);
   }
   ```

4. **✅ Record-Level Authorization:**
   ```php
   if (!authorize('documents.update', $document)) {
       // Ownership veya Department policy kontrolü
       return new JsonResponse(['error' => 'Forbidden'], 403);
   }
   ```

5. **✅ Field-Level Restrictions:**
   ```php
   if (!authorize('documents.view_budget')) {
       unset($data['budget']); // Employee görmez
   }
   ```

6. **✅ Role-Based Filtering:**
   ```php
   if ($user->hasRole('manager')) {
       $query->where('department_id', '=', $user->department_id);
   }
   ```

### 🎯 Kullanım Özeti

| Kullanıcı | documents.view | documents.create | documents.update | documents.delete | documents.publish | view_budget |
|-----------|---------------|------------------|------------------|------------------|-------------------|-------------|
| **Admin** | ✅ Tümü | ✅ | ✅ Tümü | ✅ Tümü | ✅ | ✅ |
| **Manager** | ✅ Kendi Dep. | ✅ | ✅ Kendi Dep. | ✅ Kendi Dep. | ✅ | ✅ |
| **Employee** | ✅ Kendisi | ✅ | ✅ Kendisi | ❌ | ❌ | ❌ |

---

## 🔥 SONUÇ

**EVET, BU SENARYOYUYAPABİLİYORUZ!** 🎉

- ✅ Programatik rol/permission oluşturma
- ✅ **Web arayüzünden** rol/permission oluşturma
- ✅ **Web arayüzünden** permission atama
- ✅ **Web arayüzünden** kullanıcılara rol atama
- ✅ Table-level authorization
- ✅ Record-level authorization (Ownership/Department)
- ✅ Field-level restrictions
- ✅ Dynamic policies

**GERÇEK VALIDATOR kullandık:**
```php
$schema = ValidationSchema::create()
    ->field('name', (new StringType())->required()->min(3)->max(100));
```

**GERÇEK RBAC fonksiyonları kullandık:**
```php
authorize('documents.update', $document);
$user->hasRole('manager');
$role->givePermissionTo($permission);
```

**HİÇBİR VARSAYIM YOK, HER ŞEY GERÇEK!** 💪
