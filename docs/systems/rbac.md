# RBAC (Role-Based Access Control)

## Hızlı Başlangıç

### 1. Migration Çalıştır

```bash
php conduit migrate
```

### 2. User Model'e HasRoles Trait Ekle

```php
use Conduit\Authorization\Traits\HasRoles;

class User extends Model {
    use HasRoles;
}
```

### 3. Role ve Permission Oluştur

```php
use Conduit\Authorization\Models\Role;
use Conduit\Authorization\Models\Permission;

// Roller oluştur
$admin = Role::create([
    'name' => 'Admin',
    'slug' => 'admin',
    'description' => 'Sistem yöneticisi',
]);

$editor = Role::create([
    'name' => 'Editor',
    'slug' => 'editor',
]);

// Permission'lar oluştur
$viewPosts = Permission::createOrGet('posts', 'view');
$createPosts = Permission::createOrGet('posts', 'create');
$updatePosts = Permission::createOrGet('posts', 'update');
$deletePosts = Permission::createOrGet('posts', 'delete');

// Role'e permission ver
$admin->givePermissionTo([$viewPosts, $createPosts, $updatePosts, $deletePosts]);
$editor->givePermissionTo([$viewPosts, $createPosts, $updatePosts]);
```

### 4. User'a Role Ata

```php
$user = User::find(1);
$user->assignRole('admin');

// Veya
$user->assignRole($admin);
```

### 5. Kontrol Et

```php
// Permission kontrolü
if ($user->can('posts.delete')) {
    $post->delete();
}

// Role kontrolü
if ($user->hasRole('admin')) {
    // Admin işlemleri
}
```

---

## Route Koruma

```php
// Middleware ile
$router->delete('/posts/{id}', 'PostController@destroy')
    ->middleware('permission:posts.delete');

// Çoklu permission (OR)
$router->get('/admin', 'AdminController@index')
    ->middleware('permission:admin.view|super.admin');

// Çoklu permission (AND)
$router->post('/sensitive', 'SensitiveController@store')
    ->middleware('permission:posts.create&posts.approve');
```

---

## Record-Level Authorization

### Ownership Policy

```php
use Conduit\Authorization\Models\PermissionPolicy;

// Sadece kendi postlarını güncelleyebilir
$updatePermission = Permission::findByName('posts.update');
PermissionPolicy::createOwnershipPolicy($updatePermission->id, 'user_id');

// Kullanım
$post = Post::find(1);

if (authorize('posts.update', $post)) {
    // User bu postu güncelleyebilir (sahip)
}
```

### Controller'da Kullanım

```php
class PostController {
    public function update(int $id, Request $request): JsonResponse {
        $post = Post::find($id);

        if (!authorize('posts.update', $post)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $post->update($request->all());

        return new JsonResponse($post);
    }
}
```

---

## Field-Level Restrictions

```php
// Sensitive field'ları gizle/maskele
$viewPermission = Permission::findByName('users.view');

$viewPermission->addFieldRestriction('password', 'hidden');
$viewPermission->addFieldRestriction('ssn', 'masked', '###-##-####');
$viewPermission->addFieldRestriction('salary', 'masked', '***');

// Kullanım
$restrictor = new FieldRestrictor($user);
$sanitized = $restrictor->applyRestrictions('users.view', $userModel);
// password gizli, ssn ve salary maskelenmiş
```

---

## Helper Functions

```php
// Permission check
can('posts.delete'); // true/false
cannot('users.delete'); // true/false

// Role check
hasRole('admin'); // true/false
hasPermission('posts.create'); // true/false

// Authorization
authorize('posts.update', $post); // true/false

// Field restrictions
$sanitized = applyFieldRestrictions('users.view', $user);
```

---

## Tam Örnek: Blog

```php
// Seeder
class RBACSeeder {
    public function run() {
        // Roller
        $admin = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $author = Role::create(['name' => 'Author', 'slug' => 'author']);
        $reader = Role::create(['name' => 'Reader', 'slug' => 'reader']);

        // Permissions
        $viewPosts = Permission::createOrGet('posts', 'view');
        $createPosts = Permission::createOrGet('posts', 'create');
        $updatePosts = Permission::createOrGet('posts', 'update');
        $deletePosts = Permission::createOrGet('posts', 'delete');

        // Admin - hepsini yapabilir
        $admin->givePermissionTo([$viewPosts, $createPosts, $updatePosts, $deletePosts]);

        // Author - okuyabilir, oluşturabilir, sadece kendi yazılarını güncelleyebilir
        $author->givePermissionTo([$viewPosts, $createPosts, $updatePosts]);
        PermissionPolicy::createOwnershipPolicy($updatePosts->id, 'user_id');

        // Reader - sadece okuyabilir
        $reader->givePermissionTo($viewPosts);
    }
}

// Route
$router->group(['middleware' => 'auth'], function($router) {
    $router->get('/posts', 'PostController@index'); // Herkes
    $router->post('/posts', 'PostController@store')
        ->middleware('permission:posts.create'); // Author ve Admin
    $router->put('/posts/{id}', 'PostController@update')
        ->middleware('permission:posts.update'); // Ownership kontrolü yapılır
    $router->delete('/posts/{id}', 'PostController@destroy')
        ->middleware('permission:posts.delete'); // Sadece Admin
});

// Controller
class PostController {
    public function update(int $id, Request $request): JsonResponse {
        $post = Post::find($id);

        // Ownership policy otomatik kontrol edilir
        if (!authorize('posts.update', $post)) {
            return new JsonResponse(['error' => 'Bu yazıyı düzenleyemezsiniz'], 403);
        }

        $post->update($request->all());

        return new JsonResponse($post);
    }
}
```

Detaylı bilgi için: `examples/RBAC_USAGE.md`
