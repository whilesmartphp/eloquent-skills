<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_uuid_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_uuid_workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_agent_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type');
            $table->string('owner_id', 64);
            $table->string('scope_key', 64)->default('default');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_agent_profiles');
        Schema::dropIfExists('test_uuid_workspaces');
        Schema::dropIfExists('test_workspaces');
        Schema::dropIfExists('test_uuid_users');
        Schema::dropIfExists('test_users');
    }
};
