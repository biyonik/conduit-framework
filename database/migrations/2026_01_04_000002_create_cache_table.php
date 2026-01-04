<?php

declare(strict_types=1);

use Conduit\Database\Schema\Migration;
use Conduit\Database\Schema\Schema;
use Conduit\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key', 255)->primary();
            $table->longText('value');
            $table->unsignedInteger('expiration')->default(0);

            $table->index('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
    }
};
