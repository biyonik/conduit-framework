<?php

declare(strict_types=1);

use Conduit\Database\Schema\Migration;
use Conduit\Database\Schema\Schema;
use Conduit\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('expires_at');
            $table->unsignedInteger('created_at');
            
            $table->index('expires_at');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('rate_limits');
    }
};
