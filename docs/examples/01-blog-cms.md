# Örnek Uygulama 1: Blog/CMS Platformu

## Proje Özeti

Çok yazarlı blog platformu:
- Yazarlar kendi yazılarını yönetebilir
- Editorler tüm yazıları düzenleyebilir
- Adminler her şeyi yapabilir
- Kategoriler, etiketler, yorumlar
- Medya yönetimi
- Cache optimize

---

## 1. Database Schema

### Migration Dosyaları

```php
// database/migrations/2026_01_05_create_posts_table.php
use Conduit\Database\Schema\Migration;
use Conduit\Database\Schema\Schema;
use Conduit\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('featured_image', 255)->nullable();
            $table->string('status', 20)->default('draft'); // draft, published, archived
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('published_at')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');

            $table->index('slug');
            $table->index('status');
            $table->index('published_at');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedInteger('created_at');
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('tag_id');

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');

            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('author_name', 100)->nullable();
            $table->string('author_email', 255)->nullable();
            $table->text('content');
            $table->string('status', 20)->default('pending'); // pending, approved, spam
            $table->unsignedInteger('created_at');

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['post_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('categories');
    }
};
```

---

## 2. Models

### Post Model

```php
// app/Models/Post.php
namespace App\Models;

use Conduit\Database\Model;
use Conduit\Authorization\Traits\AppliesPermissionScopes;

class Post extends Model {
    use AppliesPermissionScopes;

    protected string $table = 'posts';
    protected string $permissionResource = 'posts';

    protected array $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'published_at',
    ];

    protected array $casts = [
        'id' => 'int',
        'user_id' => 'int',
        'category_id' => 'int',
        'view_count' => 'int',
        'published_at' => 'int',
        'created_at' => 'int',
        'updated_at' => 'int',
    ];

    // Relationships
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function tags() {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');
    }

    public function comments() {
        return $this->hasMany(Comment::class, 'post_id');
    }

    // Scopes
    public function scopePublished($query) {
        return $query->where('status', '=', 'published')
                     ->where('published_at', '<=', time());
    }

    public function scopeDraft($query) {
        return $query->where('status', '=', 'draft');
    }

    // Helper methods
    public function isPublished(): bool {
        return $this->status === 'published' && $this->published_at <= time();
    }

    public function incrementViewCount(): void {
        $this->view_count++;
        $this->save();
    }

    public static function generateSlug(string $title): string {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);

        // Eğer slug varsa, unique yap
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', '=', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }
}
```

### Category & Tag Models

```php
// app/Models/Category.php
class Category extends Model {
    protected string $table = 'categories';

    protected array $fillable = ['name', 'slug', 'description'];

    public function posts() {
        return $this->hasMany(Post::class, 'category_id');
    }
}

// app/Models/Tag.php
class Tag extends Model {
    protected string $table = 'tags';

    protected array $fillable = ['name', 'slug'];

    public function posts() {
        return $this->belongsToMany(Post::class, 'post_tag', 'tag_id', 'post_id');
    }
}

// app/Models/Comment.php
class Comment extends Model {
    protected string $table = 'comments';

    protected array $fillable = [
        'post_id',
        'user_id',
        'author_name',
        'author_email',
        'content',
        'status',
    ];

    public function post() {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approve(): void {
        $this->status = 'approved';
        $this->save();
    }
}
```

---

## 3. RBAC Setup

### Seeder

```php
// database/seeders/BlogRBACSeeder.php
use Conduit\Authorization\Models\Role;
use Conduit\Authorization\Models\Permission;
use Conduit\Authorization\Models\PermissionPolicy;

class BlogRBACSeeder {
    public function run(): void {
        // Roller oluştur
        $admin = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Tam yetki',
        ]);

        $editor = Role::create([
            'name' => 'Editor',
            'slug' => 'editor',
            'description' => 'Tüm yazıları düzenleyebilir',
        ]);

        $author = Role::create([
            'name' => 'Author',
            'slug' => 'author',
            'description' => 'Kendi yazılarını yönetir',
        ]);

        // Post permissions
        $viewPosts = Permission::createOrGet('posts', 'view');
        $createPosts = Permission::createOrGet('posts', 'create');
        $updatePosts = Permission::createOrGet('posts', 'update');
        $deletePosts = Permission::createOrGet('posts', 'delete');
        $publishPosts = Permission::createOrGet('posts', 'publish');

        // Category permissions
        $manageCategories = Permission::createOrGet('categories', 'manage');

        // Comment permissions
        $approveComments = Permission::createOrGet('comments', 'approve');
        $deleteComments = Permission::createOrGet('comments', 'delete');

        // Admin - her şey
        $admin->givePermissionTo([
            $viewPosts, $createPosts, $updatePosts, $deletePosts, $publishPosts,
            $manageCategories, $approveComments, $deleteComments,
        ]);

        // Editor - tüm yazılar
        $editor->givePermissionTo([
            $viewPosts, $createPosts, $updatePosts, $publishPosts,
            $approveComments,
        ]);

        // Author - sadece kendi yazıları
        $author->givePermissionTo([
            $viewPosts, $createPosts, $updatePosts,
        ]);

        // Ownership policy: Author sadece kendi yazılarını güncelleyebilir
        PermissionPolicy::createOwnershipPolicy($updatePosts->id, 'user_id', 100);
        PermissionPolicy::createOwnershipPolicy($deletePosts->id, 'user_id', 100);
    }
}
```

---

## 4. Controllers

### PostController

```php
// app/Controllers/PostController.php
namespace App\Controllers;

use App\Models\Post;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class PostController {
    // Public: Yayınlanmış yazılar
    public function index(Request $request): JsonResponse {
        $page = (int) $request->query('page', 1);
        $cacheKey = "published_posts_page_{$page}";

        $posts = cache()->remember($cacheKey, 600, function() use ($page) {
            return Post::published()
                ->orderBy('published_at', 'DESC')
                ->limit(20)
                ->offset(($page - 1) * 20)
                ->with(['category', 'user', 'tags'])
                ->get()
                ->toArray();
        });

        return new JsonResponse($posts);
    }

    // Public: Tek yazı göster
    public function show(string $slug): JsonResponse {
        $cacheKey = "post_{$slug}";

        $post = cache()->remember($cacheKey, 600, function() use ($slug) {
            $post = Post::where('slug', '=', $slug)
                ->with(['category', 'user', 'tags', 'comments'])
                ->first();

            return $post ? $post->toArray() : null;
        });

        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], 404);
        }

        // View count arttır (async job ile)
        \App\Jobs\IncrementPostViewCount::dispatch($post['id']);

        return new JsonResponse($post);
    }

    // Auth required: Yeni yazı
    public function store(Request $request): JsonResponse {
        // Permission check (middleware'de yapılır)

        $data = $request->only(['title', 'content', 'excerpt', 'category_id']);
        $data['user_id'] = $request->getAttribute('user')->id;
        $data['slug'] = Post::generateSlug($data['title']);
        $data['status'] = 'draft';

        $post = Post::create($data);

        // Tags ekle
        if ($request->has('tags')) {
            $tagIds = $this->syncTags($request->input('tags'));
            $post->tags()->sync($tagIds);
        }

        // Cache invalidate
        cache()->delete('published_posts_page_1');

        logger()->info('Post created', ['post_id' => $post->id, 'user_id' => $data['user_id']]);

        return new JsonResponse($post, 201);
    }

    // Auth + ownership: Yazı güncelle
    public function update(int $id, Request $request): JsonResponse {
        $post = Post::find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], 404);
        }

        // Ownership policy kontrolü
        if (!authorize('posts.update', $post)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $data = $request->only(['title', 'content', 'excerpt', 'category_id', 'featured_image']);

        // Başlık değiştiyse slug güncelle
        if (isset($data['title']) && $data['title'] !== $post->title) {
            $data['slug'] = Post::generateSlug($data['title']);
        }

        $post->update($data);

        // Tags güncelle
        if ($request->has('tags')) {
            $tagIds = $this->syncTags($request->input('tags'));
            $post->tags()->sync($tagIds);
        }

        // Cache invalidate
        cache()->delete("post_{$post->slug}");
        cache()->clear(); // Veya pattern-based silme

        return new JsonResponse($post);
    }

    // Admin/Editor: Yayınla
    public function publish(int $id): JsonResponse {
        $post = Post::find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], 404);
        }

        if (!authorize('posts.publish')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $post->update([
            'status' => 'published',
            'published_at' => time(),
        ]);

        // Cache invalidate
        cache()->clear();

        // Email gönder (queue)
        \App\Jobs\NotifySubscribers::dispatch($post->id);

        return new JsonResponse($post);
    }

    // Helper: Tag senkronizasyonu
    private function syncTags(array $tagNames): array {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $slug = strtolower(trim($tagName));
            $tag = Tag::where('slug', '=', $slug)->first();

            if (!$tag) {
                $tag = Tag::create(['name' => $tagName, 'slug' => $slug]);
            }

            $tagIds[] = $tag->id;
        }

        return $tagIds;
    }
}
```

---

## 5. Routes

```php
// routes/web.php
use Conduit\Routing\Router;

$router = app(Router::class);

// Public routes
$router->get('/', 'HomeController@index')->name('home');
$router->get('/posts', 'PostController@index')->name('posts.index');
$router->get('/posts/{slug}', 'PostController@show')->name('posts.show');
$router->get('/category/{slug}', 'CategoryController@show')->name('category.show');

// Auth routes
$router->post('/login', 'AuthController@login')->name('login');
$router->post('/register', 'AuthController@register')->name('register');

// Protected routes
$router->group(['middleware' => 'auth'], function($router) {

    // Author panel
    $router->group(['prefix' => 'my'], function($router) {
        $router->get('/posts', 'MyPostController@index');
        $router->post('/posts', 'PostController@store')
            ->middleware('permission:posts.create');
        $router->put('/posts/{id}', 'PostController@update')
            ->middleware('permission:posts.update');
        $router->delete('/posts/{id}', 'PostController@destroy')
            ->middleware('permission:posts.delete');
    });

    // Editor/Admin routes
    $router->group(['prefix' => 'admin', 'middleware' => 'role:admin|editor'], function($router) {
        $router->get('/posts', 'Admin\PostController@index');
        $router->put('/posts/{id}/publish', 'PostController@publish')
            ->middleware('permission:posts.publish');

        $router->get('/comments/pending', 'Admin\CommentController@pending');
        $router->put('/comments/{id}/approve', 'CommentController@approve')
            ->middleware('permission:comments.approve');

        // Categories (sadece admin)
        $router->resource('categories', 'Admin\CategoryController')
            ->middleware('permission:categories.manage');
    });
});
```

---

## 6. Jobs (Queue)

```php
// app/Jobs/IncrementPostViewCount.php
namespace App\Jobs;

use Conduit\Queue\Job;
use App\Models\Post;

class IncrementPostViewCount extends Job {
    public function __construct(private int $postId) {}

    public function handle(): void {
        $post = Post::find($this->postId);

        if ($post) {
            $post->incrementViewCount();
        }
    }
}

// app/Jobs/NotifySubscribers.php
class NotifySubscribers extends Job {
    public function __construct(private int $postId) {}

    public function handle(): void {
        $post = Post::find($this->postId);

        if (!$post) {
            return;
        }

        // Abonelere email gönder
        $subscribers = Subscriber::all();

        foreach ($subscribers as $subscriber) {
            mail_queue(
                $subscriber->email,
                "Yeni Yazı: {$post->title}",
                $this->renderEmailTemplate($post)
            );
        }

        logger()->info('Subscribers notified', ['post_id' => $this->postId]);
    }

    private function renderEmailTemplate(Post $post): string {
        return "
            <h1>{$post->title}</h1>
            <p>{$post->excerpt}</p>
            <a href='https://yourblog.com/posts/{$post->slug}'>Devamını Oku</a>
        ";
    }
}
```

---

## 7. Medya Yönetimi

```php
// app/Controllers/MediaController.php
class MediaController {
    public function upload(Request $request): JsonResponse {
        if (!$request->hasFile('file')) {
            return new JsonResponse(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');

        // Validate
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB
            return new JsonResponse(['error' => 'File too large'], 400);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

        if (!in_array($file['type'], $allowedTypes)) {
            return new JsonResponse(['error' => 'Invalid file type'], 400);
        }

        // Generate unique filename
        $filename = uniqid() . '_' . $file['name'];
        $path = 'uploads/' . date('Y/m') . '/' . $filename;

        // Save to storage
        $contents = file_get_contents($file['tmp_name']);
        storage()->put($path, $contents);

        // Save to database
        $media = Media::create([
            'filename' => $filename,
            'path' => $path,
            'size' => $file['size'],
            'mime_type' => $file['type'],
            'user_id' => $request->getAttribute('user')->id,
        ]);

        return new JsonResponse([
            'id' => $media->id,
            'url' => storage()->url($path),
            'filename' => $filename,
        ], 201);
    }
}
```

---

## 8. Cache Stratejisi

```php
// Post listesi - 10 dakika cache
cache()->remember('published_posts_page_1', 600, fn() => Post::published()->get());

// Tek post - 10 dakika cache
cache()->remember("post_{$slug}", 600, fn() => Post::find($slug));

// Categories - Süresiz (az değişir)
cache()->rememberForever('all_categories', fn() => Category::all());

// Popular posts - 1 saat
cache()->remember('popular_posts', 3600, fn() =>
    Post::orderBy('view_count', 'DESC')->limit(10)->get()
);

// Cache invalidation
// Post oluşturulduğunda/güncellendiğinde:
cache()->delete("post_{$slug}");
cache()->delete('published_posts_page_1');
cache()->delete('popular_posts');
```

---

## 9. Testing

```php
// tests/Feature/PostTest.php
class PostTest extends TestCase {
    public function testGuestCanViewPublishedPosts() {
        $response = $this->get('/posts');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAuthorCanCreatePost() {
        $user = User::factory()->create();
        $user->assignRole('author');

        $response = $this->actingAs($user)->post('/my/posts', [
            'title' => 'Test Post',
            'content' => 'Test content',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testAuthorCannotUpdateOthersPost() {
        $author1 = User::factory()->create();
        $author1->assignRole('author');

        $author2 = User::factory()->create();
        $author2->assignRole('author');

        $post = Post::create([
            'title' => 'Test',
            'user_id' => $author1->id,
        ]);

        $response = $this->actingAs($author2)->put("/my/posts/{$post->id}", [
            'title' => 'Hacked!',
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }
}
```

---

## Özet

Bu blog/CMS platformu şunları içerir:
- ✅ Multi-role RBAC (admin, editor, author)
- ✅ Ownership-based access control
- ✅ Cache optimization
- ✅ Queue jobs (view count, emails)
- ✅ File storage (media upload)
- ✅ RESTful API
- ✅ Logging
- ✅ Testing ready

**Çalıştırma:**
```bash
php conduit migrate
php conduit db:seed BlogRBACSeeder
php conduit queue:work & # Background queue worker
```
