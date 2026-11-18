<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('skills.tables', []);
        $skills = $tables['skills'] ?? 'skills';
        $versions = $tables['versions'] ?? 'skill_versions';
        $availabilities = $tables['availabilities'] ?? 'skill_version_availabilities';
        $imports = $tables['imports'] ?? 'skill_imports';
        $bindings = $tables['bindings'] ?? 'skill_bindings';

        Schema::create($skills, function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('owner_type');
            $table->string('owner_id', 64);
            $table->string('scope_key', 64)->default('default');
            $table->string('name', 64);
            $table->string('active_name', 64)->nullable();
            $table->string('description', 1024)->nullable();
            $table->unsignedInteger('draft_revision')->default(1);
            $table->json('draft_files');
            $table->json('draft_provenance')->nullable();
            $table->unsignedInteger('latest_version_number')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_type', 'owner_id', 'scope_key', 'active_name'], 'skills_owner_scope_name_unique');
            $table->index(['owner_type', 'owner_id', 'scope_key'], 'skills_owner_scope_index');
        });

        Schema::create($versions, function (Blueprint $table) use ($skills) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('skill_id')->constrained($skills)->restrictOnDelete();
            $table->string('name', 64);
            $table->unsignedInteger('number');
            $table->json('files');
            $table->string('content_hash', 128);
            $table->json('frontmatter')->nullable();
            $table->longText('body');
            $table->json('provenance')->nullable();
            $table->string('created_by_type')->nullable();
            $table->string('created_by_id', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['skill_id', 'number']);
        });

        Schema::create($availabilities, function (Blueprint $table) use ($versions) {
            $table->id();
            $table->foreignId('skill_version_id')->unique()->constrained($versions)->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('reason', 500)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('changed_by_type')->nullable();
            $table->string('changed_by_id', 64)->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create($imports, function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('owner_type');
            $table->string('owner_id', 64);
            $table->string('scope_key', 64)->default('default');
            $table->string('source', 64);
            $table->string('reference', 2048);
            $table->string('status', 20)->default('pending');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'scope_key'], 'skill_imports_owner_scope_index');
        });

        Schema::create($bindings, function (Blueprint $table) use ($skills, $versions) {
            $table->id();
            $table->string('skillable_type');
            $table->string('skillable_id', 64);
            $table->foreignId('skill_id')->constrained($skills)->cascadeOnDelete();
            $table->foreignId('skill_version_id')->constrained($versions)->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['skillable_type', 'skillable_id', 'skill_id'], 'skill_bindings_skillable_skill_unique');
            $table->index(['skillable_type', 'skillable_id'], 'skill_bindings_skillable_index');
        });
    }

    public function down(): void
    {
        $tables = config('skills.tables', []);

        Schema::dropIfExists($tables['bindings'] ?? 'skill_bindings');
        Schema::dropIfExists($tables['imports'] ?? 'skill_imports');
        Schema::dropIfExists($tables['availabilities'] ?? 'skill_version_availabilities');
        Schema::dropIfExists($tables['versions'] ?? 'skill_versions');
        Schema::dropIfExists($tables['skills'] ?? 'skills');
    }
};
