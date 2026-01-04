# Örnek Uygulama 3: SaaS Multi-Tenant Platformu

## Proje Özeti

Multi-tenant Proje Yönetim SaaS:
- Her şirket kendi workspace'inde çalışır
- Takım üyeleri ve roller
- Proje, görev, zaman takibi
- Abonelik planları (Free, Pro, Enterprise)
- Kullanım limitleri
- Faturalama sistemi
- Tenant isolation (veri izolasyonu)
- Team-based RBAC

---

## 1. Database Schema

### Migration Dosyaları

```php
// database/migrations/2026_01_05_create_saas_tables.php
use Conduit\Database\Schema\Migration;
use Conduit\Database\Schema\Schema;
use Conduit\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        // Tenants (Workspaces/Organizations)
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 100)->unique();
            $table->string('domain', 255)->nullable()->unique(); // custom domain
            $table->string('plan', 50)->default('free'); // free, pro, enterprise
            $table->string('status', 20)->default('active'); // active, suspended, cancelled
            $table->unsignedInteger('trial_ends_at')->nullable();
            $table->unsignedInteger('subscription_ends_at')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->index('slug');
            $table->index('status');
        });

        // Tenant members (users in tenants)
        Schema::create('tenant_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 50); // owner, admin, member, viewer
            $table->string('status', 20)->default('active'); // active, invited, suspended
            $table->unsignedInteger('invited_at')->nullable();
            $table->unsignedInteger('joined_at')->nullable();
            $table->unsignedInteger('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['tenant_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        // Projects (tenant-scoped)
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id'); // ISOLATION KEY
            $table->string('name', 255);
            $table->string('key', 20); // PRJ, WEB, etc.
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active, archived, completed
            $table->unsignedBigInteger('owner_id'); // Project owner
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');

            $table->index('tenant_id'); // CRITICAL FOR TENANT ISOLATION
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'key']);
        });

        // Tasks (tenant-scoped)
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id'); // ISOLATION KEY
            $table->unsignedBigInteger('project_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo'); // todo, in_progress, review, done
            $table->string('priority', 20)->default('medium'); // low, medium, high, critical
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->unsignedBigInteger('reporter_id');
            $table->unsignedInteger('estimated_hours')->nullable();
            $table->unsignedInteger('due_date')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('assignee_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reporter_id')->references('id')->on('users')->onDelete('restrict');

            $table->index('tenant_id'); // CRITICAL
            $table->index(['tenant_id', 'project_id']);
            $table->index(['tenant_id', 'assignee_id']);
            $table->index('status');
        });

        // Time tracking (tenant-scoped)
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id'); // ISOLATION KEY
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->text('description')->nullable();
            $table->unsignedInteger('hours');
            $table->unsignedInteger('logged_at');
            $table->unsignedInteger('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('tenant_id'); // CRITICAL
            $table->index(['tenant_id', 'task_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        // Subscriptions
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('plan', 50); // free, pro, enterprise
            $table->string('status', 20); // active, cancelled, expired, past_due
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('billing_cycle', 20)->default('monthly'); // monthly, yearly
            $table->unsignedInteger('current_period_start');
            $table->unsignedInteger('current_period_end');
            $table->unsignedInteger('cancelled_at')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
        });

        // Usage tracking (for limits)
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('metric', 50); // projects, tasks, storage_mb, api_calls
            $table->unsignedInteger('value');
            $table->unsignedInteger('period'); // YYYYMM format (202601)
            $table->unsignedInteger('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'metric', 'period']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('tenant_members');
        Schema::dropIfExists('tenants');
    }
};
```

---

## 2. Models

### Tenant Model

```php
// app/Models/Tenant.php
namespace App\Models;

use Conduit\Database\Model;

class Tenant extends Model {
    protected string $table = 'tenants';

    protected array $fillable = [
        'name',
        'slug',
        'domain',
        'plan',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
        'settings',
    ];

    protected array $casts = [
        'settings' => 'json',
        'trial_ends_at' => 'int',
        'subscription_ends_at' => 'int',
    ];

    // Relationships
    public function members() {
        return $this->hasMany(TenantMember::class, 'tenant_id');
    }

    public function projects() {
        return $this->hasMany(Project::class, 'tenant_id');
    }

    public function subscription() {
        return $this->hasOne(Subscription::class, 'tenant_id')
            ->where('status', '=', 'active');
    }

    // Plan limits
    public function getPlanLimits(): array {
        return match($this->plan) {
            'free' => [
                'projects' => 3,
                'members' => 5,
                'tasks_per_project' => 50,
                'storage_mb' => 100,
            ],
            'pro' => [
                'projects' => 50,
                'members' => 50,
                'tasks_per_project' => 1000,
                'storage_mb' => 10000, // 10GB
            ],
            'enterprise' => [
                'projects' => -1, // unlimited
                'members' => -1,
                'tasks_per_project' => -1,
                'storage_mb' => -1,
            ],
            default => [],
        };
    }

    public function canCreateProject(): bool {
        $limits = $this->getPlanLimits();

        if ($limits['projects'] === -1) {
            return true; // Unlimited
        }

        $currentCount = $this->projects()->count();
        return $currentCount < $limits['projects'];
    }

    public function canAddMember(): bool {
        $limits = $this->getPlanLimits();

        if ($limits['members'] === -1) {
            return true;
        }

        $currentCount = $this->members()->where('status', '=', 'active')->count();
        return $currentCount < $limits['members'];
    }

    public function isOnTrial(): bool {
        return $this->trial_ends_at && $this->trial_ends_at > time();
    }

    public function isActive(): bool {
        return $this->status === 'active';
    }
}
```

### Project Model (Tenant-Scoped)

```php
// app/Models/Project.php
namespace App\Models;

use Conduit\Database\Model;
use App\Traits\BelongsToTenant;

class Project extends Model {
    use BelongsToTenant; // Auto-scope queries to current tenant

    protected string $table = 'projects';

    protected array $fillable = [
        'tenant_id',
        'name',
        'key',
        'description',
        'status',
        'owner_id',
    ];

    protected array $casts = [
        'id' => 'int',
        'tenant_id' => 'int',
        'owner_id' => 'int',
    ];

    // Relationships
    public function tenant() {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function owner() {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks() {
        return $this->hasMany(Task::class, 'project_id');
    }

    // Helper
    public function canAddTask(): bool {
        $tenant = $this->tenant()->first();
        $limits = $tenant->getPlanLimits();

        if ($limits['tasks_per_project'] === -1) {
            return true;
        }

        $currentCount = $this->tasks()->count();
        return $currentCount < $limits['tasks_per_project'];
    }
}
```

### Task Model (Tenant-Scoped)

```php
// app/Models/Task.php
namespace App\Models;

use Conduit\Database\Model;
use App\Traits\BelongsToTenant;

class Task extends Model {
    use BelongsToTenant;

    protected string $table = 'tasks';

    protected array $fillable = [
        'tenant_id',
        'project_id',
        'title',
        'description',
        'status',
        'priority',
        'assignee_id',
        'reporter_id',
        'estimated_hours',
        'due_date',
    ];

    protected array $casts = [
        'id' => 'int',
        'tenant_id' => 'int',
        'project_id' => 'int',
        'assignee_id' => 'int',
        'reporter_id' => 'int',
        'estimated_hours' => 'int',
        'due_date' => 'int',
    ];

    // Relationships
    public function project() {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function assignee() {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter() {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function timeEntries() {
        return $this->hasMany(TimeEntry::class, 'task_id');
    }

    // Helper
    public function getTotalHours(): int {
        return $this->timeEntries()->sum('hours');
    }
}
```

### Tenant-Scoped Trait

```php
// app/Traits/BelongsToTenant.php
namespace App\Traits;

trait BelongsToTenant {
    /**
     * Boot the trait - add global scope
     */
    protected static function bootBelongsToTenant(): void {
        // Auto-scope all queries to current tenant
        static::addGlobalScope('tenant', function ($query) {
            $tenantId = app('current_tenant_id');

            if ($tenantId) {
                $query->where($query->getModel()->getTable() . '.tenant_id', '=', $tenantId);
            }
        });

        // Auto-fill tenant_id on create
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = app('current_tenant_id');
            }
        });
    }

    // Relationship
    public function tenant() {
        return $this->belongsTo(\App\Models\Tenant::class, 'tenant_id');
    }
}
```

---

## 3. Middleware - Tenant Context

### TenantMiddleware

```php
// app/Middleware/TenantMiddleware.php
namespace App\Middleware;

use App\Models\Tenant;
use App\Models\TenantMember;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class TenantMiddleware {
    public function handle(Request $request, callable $next) {
        $user = $request->getAttribute('user');

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Get tenant from header or subdomain
        $tenantSlug = $request->getHeader('X-Tenant-ID')
            ?? $this->getTenantFromSubdomain($request);

        if (!$tenantSlug) {
            return new JsonResponse([
                'error' => 'Tenant not specified',
                'code' => 'TENANT_REQUIRED',
            ], 400);
        }

        // Find tenant
        $tenant = Tenant::where('slug', '=', $tenantSlug)->first();

        if (!$tenant) {
            return new JsonResponse([
                'error' => 'Tenant not found',
                'code' => 'TENANT_NOT_FOUND',
            ], 404);
        }

        if (!$tenant->isActive()) {
            return new JsonResponse([
                'error' => 'Tenant is not active',
                'code' => 'TENANT_INACTIVE',
            ], 403);
        }

        // Check user membership
        $membership = TenantMember::where('tenant_id', '=', $tenant->id)
            ->where('user_id', '=', $user->id)
            ->where('status', '=', 'active')
            ->first();

        if (!$membership) {
            return new JsonResponse([
                'error' => 'Access denied to this tenant',
                'code' => 'TENANT_ACCESS_DENIED',
            ], 403);
        }

        // Set current tenant context (global)
        app()->instance('current_tenant_id', $tenant->id);
        app()->instance('current_tenant', $tenant);
        app()->instance('current_tenant_role', $membership->role);

        // Attach to request
        $request->setAttribute('tenant', $tenant);
        $request->setAttribute('tenant_role', $membership->role);

        return $next($request);
    }

    private function getTenantFromSubdomain(Request $request): ?string {
        $host = $request->getHeader('Host') ?? '';
        $parts = explode('.', $host);

        if (count($parts) >= 3) {
            // subdomain.example.com -> 'subdomain'
            return $parts[0];
        }

        return null;
    }
}
```

---

## 4. Controllers

### ProjectController (Tenant-Scoped)

```php
// app/Controllers/ProjectController.php
namespace App\Controllers;

use App\Models\Project;
use App\Models\Tenant;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class ProjectController {
    // GET /projects
    public function index(Request $request): JsonResponse {
        $tenant = $request->getAttribute('tenant');

        // BelongsToTenant trait automatically scopes this query
        $projects = Project::with(['owner'])
            ->orderBy('created_at', 'DESC')
            ->get();

        return new JsonResponse([
            'data' => $projects->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'key' => $p->key,
                'status' => $p->status,
                'owner' => [
                    'id' => $p->owner->id,
                    'name' => $p->owner->name,
                ],
                'tasks_count' => $p->tasks()->count(),
                'created_at' => $p->created_at,
            ])->toArray(),
        ]);
    }

    // POST /projects
    public function store(Request $request): JsonResponse {
        $tenant = $request->getAttribute('tenant');
        $user = $request->getAttribute('user');
        $role = $request->getAttribute('tenant_role');

        // Only owner/admin can create projects
        if (!in_array($role, ['owner', 'admin'])) {
            return new JsonResponse([
                'error' => 'Insufficient permissions',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        // Check plan limits
        if (!$tenant->canCreateProject()) {
            return new JsonResponse([
                'error' => 'Project limit reached for your plan',
                'code' => 'LIMIT_REACHED',
                'upgrade_required' => true,
            ], 402); // Payment Required
        }

        // Validate
        $validator = validator($request->all(), [
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:20',
        ]);

        if (!$validator->passes()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create (tenant_id auto-filled by BelongsToTenant trait)
        $project = Project::create([
            'name' => $request->input('name'),
            'key' => strtoupper($request->input('key')),
            'description' => $request->input('description'),
            'owner_id' => $user->id,
            // tenant_id automatically set by trait
        ]);

        logger()->info('Project created', [
            'project_id' => $project->id,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'key' => $project->key,
            ],
        ], 201);
    }

    // GET /projects/{id}
    public function show(int $id): JsonResponse {
        // BelongsToTenant automatically ensures this belongs to current tenant
        $project = Project::with(['owner', 'tasks'])->find($id);

        if (!$project) {
            return new JsonResponse([
                'error' => 'Project not found',
                'code' => 'PROJECT_NOT_FOUND',
            ], 404);
        }

        return new JsonResponse([
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'key' => $project->key,
                'description' => $project->description,
                'status' => $project->status,
                'owner' => [
                    'id' => $project->owner->id,
                    'name' => $project->owner->name,
                ],
                'tasks' => $project->tasks()->get()->map(fn($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'status' => $t->status,
                ])->toArray(),
            ],
        ]);
    }
}
```

### TaskController (Tenant-Scoped)

```php
// app/Controllers/TaskController.php
namespace App\Controllers;

use App\Models\Task;
use App\Models\Project;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class TaskController {
    // GET /projects/{projectId}/tasks
    public function index(int $projectId, Request $request): JsonResponse {
        // Project is auto-scoped to tenant
        $project = Project::find($projectId);

        if (!$project) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        $query = Task::where('project_id', '=', $projectId);

        // Filters
        if ($status = $request->query('status')) {
            $query->where('status', '=', $status);
        }

        if ($assigneeId = $request->query('assignee_id')) {
            $query->where('assignee_id', '=', (int) $assigneeId);
        }

        $tasks = $query->with(['assignee', 'reporter'])->get();

        return new JsonResponse([
            'data' => $tasks->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority,
                'assignee' => $t->assignee ? [
                    'id' => $t->assignee->id,
                    'name' => $t->assignee->name,
                ] : null,
                'due_date' => $t->due_date,
            ])->toArray(),
        ]);
    }

    // POST /projects/{projectId}/tasks
    public function store(int $projectId, Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        $project = Project::find($projectId);

        if (!$project) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        // Check task limit
        if (!$project->canAddTask()) {
            return new JsonResponse([
                'error' => 'Task limit reached for this project',
                'code' => 'LIMIT_REACHED',
                'upgrade_required' => true,
            ], 402);
        }

        $validator = validator($request->all(), [
            'title' => 'required|string|max:255',
        ]);

        if (!$validator->passes()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $task = Task::create([
            'project_id' => $projectId,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'status' => $request->input('status', 'todo'),
            'priority' => $request->input('priority', 'medium'),
            'assignee_id' => $request->input('assignee_id'),
            'reporter_id' => $user->id,
            'estimated_hours' => $request->input('estimated_hours'),
            'due_date' => $request->input('due_date'),
            // tenant_id auto-filled
        ]);

        logger()->info('Task created', [
            'task_id' => $task->id,
            'project_id' => $projectId,
            'user_id' => $user->id,
        ]);

        return new JsonResponse(['data' => $task], 201);
    }
}
```

### TenantController

```php
// app/Controllers/TenantController.php
namespace App\Controllers;

use App\Models\Tenant;
use App\Models\TenantMember;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class TenantController {
    // POST /tenants (Create new workspace)
    public function store(Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        $validator = validator($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
        ]);

        if (!$validator->passes()) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check duplicate slug
        if (Tenant::where('slug', '=', $request->input('slug'))->exists()) {
            return new JsonResponse([
                'error' => 'Slug already taken',
                'code' => 'DUPLICATE_SLUG',
            ], 409);
        }

        // Create tenant
        $tenant = Tenant::create([
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'plan' => 'free',
            'status' => 'active',
            'trial_ends_at' => time() + (14 * 86400), // 14 days trial
        ]);

        // Add creator as owner
        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => time(),
        ]);

        logger()->info('Tenant created', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan' => $tenant->plan,
            ],
        ], 201);
    }

    // GET /tenants (List user's tenants)
    public function index(Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        $memberships = TenantMember::where('user_id', '=', $user->id)
            ->where('status', '=', 'active')
            ->with(['tenant'])
            ->get();

        return new JsonResponse([
            'data' => $memberships->map(fn($m) => [
                'tenant' => [
                    'id' => $m->tenant->id,
                    'name' => $m->tenant->name,
                    'slug' => $m->tenant->slug,
                    'plan' => $m->tenant->plan,
                ],
                'role' => $m->role,
                'joined_at' => $m->joined_at,
            ])->toArray(),
        ]);
    }

    // POST /tenants/{id}/members (Invite member)
    public function inviteMember(int $id, Request $request): JsonResponse {
        $user = $request->getAttribute('user');

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return new JsonResponse(['error' => 'Tenant not found'], 404);
        }

        // Check if user is owner/admin
        $membership = TenantMember::where('tenant_id', '=', $id)
            ->where('user_id', '=', $user->id)
            ->first();

        if (!$membership || !in_array($membership->role, ['owner', 'admin'])) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        // Check member limit
        if (!$tenant->canAddMember()) {
            return new JsonResponse([
                'error' => 'Member limit reached',
                'code' => 'LIMIT_REACHED',
                'upgrade_required' => true,
            ], 402);
        }

        $email = $request->input('email');
        $role = $request->input('role', 'member');

        // Find user by email
        $invitedUser = User::where('email', '=', $email)->first();

        if (!$invitedUser) {
            return new JsonResponse([
                'error' => 'User not found',
                'code' => 'USER_NOT_FOUND',
            ], 404);
        }

        // Create membership
        $newMembership = TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $invitedUser->id,
            'role' => $role,
            'status' => 'invited',
            'invited_at' => time(),
        ]);

        // Send invitation email (queue)
        \App\Jobs\SendTenantInvitation::dispatch($newMembership->id);

        return new JsonResponse([
            'message' => 'Invitation sent',
        ], 201);
    }
}
```

---

## 5. Routes

```php
// routes/api.php
use Conduit\Routing\Router;

$router = app(Router::class);

$router->group(['prefix' => 'api/v1', 'middleware' => 'api-auth'], function($router) {

    // Tenant management (no tenant context needed)
    $router->get('/tenants', 'TenantController@index');
    $router->post('/tenants', 'TenantController@store');

    // Tenant-scoped routes (requires tenant context)
    $router->group(['middleware' => 'tenant'], function($router) {

        // Projects
        $router->get('/projects', 'ProjectController@index');
        $router->post('/projects', 'ProjectController@store');
        $router->get('/projects/{id}', 'ProjectController@show');
        $router->put('/projects/{id}', 'ProjectController@update');

        // Tasks
        $router->get('/projects/{projectId}/tasks', 'TaskController@index');
        $router->post('/projects/{projectId}/tasks', 'TaskController@store');
        $router->put('/tasks/{id}', 'TaskController@update');

        // Time tracking
        $router->get('/tasks/{taskId}/time-entries', 'TimeEntryController@index');
        $router->post('/tasks/{taskId}/time-entries', 'TimeEntryController@store');

        // Team management
        $router->get('/members', 'TenantController@members');
        $router->post('/members/invite', 'TenantController@inviteMember');
        $router->delete('/members/{userId}', 'TenantController@removeMember');

        // Subscription
        $router->get('/subscription', 'SubscriptionController@show');
        $router->post('/subscription/upgrade', 'SubscriptionController@upgrade');
        $router->post('/subscription/cancel', 'SubscriptionController@cancel');
    });
});
```

---

## 6. Subscription Management

### SubscriptionController

```php
// app/Controllers/SubscriptionController.php
namespace App\Controllers;

use App\Models\Subscription;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class SubscriptionController {
    // GET /subscription
    public function show(Request $request): JsonResponse {
        $tenant = $request->getAttribute('tenant');

        $subscription = $tenant->subscription()->first();

        if (!$subscription) {
            return new JsonResponse([
                'data' => [
                    'plan' => $tenant->plan,
                    'status' => 'no_subscription',
                    'limits' => $tenant->getPlanLimits(),
                ],
            ]);
        }

        return new JsonResponse([
            'data' => [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'amount' => $subscription->amount,
                'billing_cycle' => $subscription->billing_cycle,
                'current_period_end' => $subscription->current_period_end,
                'limits' => $tenant->getPlanLimits(),
            ],
        ]);
    }

    // POST /subscription/upgrade
    public function upgrade(Request $request): JsonResponse {
        $tenant = $request->getAttribute('tenant');
        $role = $request->getAttribute('tenant_role');

        // Only owner can upgrade
        if ($role !== 'owner') {
            return new JsonResponse(['error' => 'Only owner can upgrade'], 403);
        }

        $newPlan = $request->input('plan'); // 'pro' or 'enterprise'

        if (!in_array($newPlan, ['pro', 'enterprise'])) {
            return new JsonResponse(['error' => 'Invalid plan'], 400);
        }

        $amount = match($newPlan) {
            'pro' => 49.00, // $49/month
            'enterprise' => 199.00, // $199/month
        };

        // Create/update subscription
        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan' => $newPlan,
            'status' => 'active',
            'amount' => $amount,
            'billing_cycle' => 'monthly',
            'current_period_start' => time(),
            'current_period_end' => time() + (30 * 86400),
        ]);

        // Update tenant plan
        $tenant->update(['plan' => $newPlan]);

        logger()->info('Subscription upgraded', [
            'tenant_id' => $tenant->id,
            'plan' => $newPlan,
        ]);

        return new JsonResponse([
            'message' => 'Subscription upgraded successfully',
            'data' => $subscription,
        ]);
    }
}
```

---

## 7. Usage Tracking

```php
// app/Services/UsageTracker.php
namespace App\Services;

use App\Models\UsageRecord;

class UsageTracker {
    public function track(int $tenantId, string $metric, int $value = 1): void {
        $period = (int) date('Ym'); // 202601

        $record = UsageRecord::where('tenant_id', '=', $tenantId)
            ->where('metric', '=', $metric)
            ->where('period', '=', $period)
            ->first();

        if ($record) {
            $record->update(['value' => $record->value + $value]);
        } else {
            UsageRecord::create([
                'tenant_id' => $tenantId,
                'metric' => $metric,
                'value' => $value,
                'period' => $period,
            ]);
        }
    }

    public function getUsage(int $tenantId, string $metric, int $period = null): int {
        $period = $period ?? (int) date('Ym');

        $record = UsageRecord::where('tenant_id', '=', $tenantId)
            ->where('metric', '=', $metric)
            ->where('period', '=', $period)
            ->first();

        return $record ? $record->value : 0;
    }
}

// Usage in controllers
$usageTracker = app(UsageTracker::class);
$usageTracker->track($tenant->id, 'projects', 1);
$usageTracker->track($tenant->id, 'api_calls', 1);
```

---

## 8. Testing

```php
// tests/Feature/TenantIsolationTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\Project;

class TenantIsolationTest extends TestCase {
    public function testUserCannotAccessOtherTenantsData() {
        // Create two tenants
        $tenant1 = Tenant::create(['name' => 'Tenant 1', 'slug' => 'tenant1', 'plan' => 'free']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'slug' => 'tenant2', 'plan' => 'free']);

        // Create users
        $user1 = User::create(['name' => 'User 1', 'email' => 'user1@test.com']);
        $user2 = User::create(['name' => 'User 2', 'email' => 'user2@test.com']);

        // Add users to tenants
        TenantMember::create(['tenant_id' => $tenant1->id, 'user_id' => $user1->id, 'role' => 'owner', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant2->id, 'user_id' => $user2->id, 'role' => 'owner', 'status' => 'active']);

        // Create projects
        $project1 = Project::create(['tenant_id' => $tenant1->id, 'name' => 'Project 1', 'key' => 'P1', 'owner_id' => $user1->id]);
        $project2 = Project::create(['tenant_id' => $tenant2->id, 'name' => 'Project 2', 'key' => 'P2', 'owner_id' => $user2->id]);

        // Set context to tenant 1
        app()->instance('current_tenant_id', $tenant1->id);

        // User 1 can see their project
        $projects = Project::all();
        $this->assertCount(1, $projects);
        $this->assertEquals('Project 1', $projects->first()->name);

        // Switch context to tenant 2
        app()->instance('current_tenant_id', $tenant2->id);

        // Now only see tenant 2 projects
        $projects = Project::all();
        $this->assertCount(1, $projects);
        $this->assertEquals('Project 2', $projects->first()->name);
    }

    public function testCannotAccessTenantWithoutMembership() {
        $user = User::create(['email' => 'test@test.com']);
        $token = ApiToken::generate($user->id)->token;

        $tenant = Tenant::create(['name' => 'Test', 'slug' => 'test', 'plan' => 'free']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID' => 'test',
        ])->get('/api/v1/projects');

        $this->assertEquals(403, $response->getStatusCode());
    }
}
```

---

## 9. Özet

Bu SaaS Multi-Tenant platformu şunları içerir:

- ✅ **Tenant Isolation**: Her tenant verisi tamamen izole
- ✅ **Global Scopes**: BelongsToTenant trait ile otomatik filtreleme
- ✅ **Team-based RBAC**: owner, admin, member, viewer rolleri
- ✅ **Subscription Management**: Free, Pro, Enterprise planları
- ✅ **Usage Limits**: Plan bazlı limitler ve kontroller
- ✅ **Multi-level Authorization**: Tenant + Role + Resource level
- ✅ **Automatic Tenant Context**: Middleware ile otomatik
- ✅ **Subdomain Support**: tenant.example.com
- ✅ **Invitation System**: Team member invitation
- ✅ **Usage Tracking**: Aylık kullanım takibi
- ✅ **Security**: Row-level security (RLS) via global scopes

**Çalıştırma:**
```bash
php conduit migrate
php conduit queue:work &

# Test
curl -X POST http://localhost:8000/api/v1/tenants \
  -H "Authorization: Bearer TOKEN" \
  -d '{"name":"My Company","slug":"mycompany"}'

curl -X GET http://localhost:8000/api/v1/projects \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: mycompany"
```

**Önemli Güvenlik Noktaları:**
1. Her model `tenant_id` içermeli
2. `BelongsToTenant` trait kullanılmalı
3. Middleware tenant context set etmeli
4. Hiçbir zaman manuel `tenant_id` filtrelemesi yapma (global scope kullan)
5. Admin bile başka tenant'a erişemez
