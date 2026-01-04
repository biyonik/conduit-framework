<?php

declare(strict_types=1);

use Conduit\Database\Schema\Migration;
use Conduit\Database\Schema\Schema;
use Conduit\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->index('slug');
        });

        // Permissions table
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('resource', 100); // e.g., 'posts', 'users', 'reports'
            $table->string('action', 50); // e.g., 'view', 'create', 'update', 'delete', 'export'
            $table->string('name', 200)->unique(); // e.g., 'posts.view', 'posts.create'
            $table->text('description')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->index(['resource', 'action']);
            $table->index('name');
        });

        // Permission policies (dynamic rules)
        Schema::create('permission_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permission_id');
            $table->string('policy_type', 50); // 'ownership', 'team', 'department', 'custom'
            $table->text('conditions'); // JSON: {"field": "user_id", "operator": "equals", "value": "{auth.id}"}
            $table->unsignedInteger('priority')->default(0); // Higher priority = checked first
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->index('permission_id');
            $table->index('policy_type');
        });

        // Field restrictions (column-level permissions)
        Schema::create('field_restrictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permission_id');
            $table->string('field_name', 100); // e.g., 'salary', 'ssn', 'password'
            $table->string('restriction_type', 20); // 'hidden', 'masked', 'readonly'
            $table->string('mask_pattern', 50)->nullable(); // e.g., '***', 'XXX-XX-####'
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->index('permission_id');
            $table->index('field_name');
        });

        // Role-User pivot table
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('created_at');

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['role_id', 'user_id']);
            $table->index('user_id');
        });

        // Permission-Role pivot table
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedInteger('created_at');

            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

            $table->unique(['permission_id', 'role_id']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('field_restrictions');
        Schema::dropIfExists('permission_policies');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
