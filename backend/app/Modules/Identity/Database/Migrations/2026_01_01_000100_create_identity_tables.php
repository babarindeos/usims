<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One permanent record per human being (PRD 7.1).
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('person_no', 20)->unique(); // PER-000123
            $table->string('title', 20)->nullable();
            $table->string('surname', 100);
            $table->string('first_name', 100);
            $table->string('other_names', 150)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('state_of_origin', 100)->nullable();
            $table->string('lga', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('merged_into_person_id')->nullable()->constrained('persons');
            $table->timestamps();

            $table->index(['surname', 'first_name']);
            $table->index('date_of_birth');
        });
        // Emails and phones are unique among persons that have them.
        DB::statement('CREATE UNIQUE INDEX persons_email_unique ON persons (lower(email)) WHERE email IS NOT NULL AND merged_into_person_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX persons_phone_unique ON persons (phone) WHERE phone IS NOT NULL AND merged_into_person_id IS NULL');

        // Human-readable identifiers with history (APP, STU, MATRIC, STAFF, ALU, JAMB ...).
        Schema::create('person_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons');
            $table->string('type', 20);
            $table->string('value', 100);
            $table->timestamp('issued_at');
            $table->timestamp('superseded_at')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('issued_by')->nullable();
            $table->timestamps();

            $table->unique(['type', 'value']);
        });
        DB::statement('CREATE UNIQUE INDEX person_identifiers_one_active ON person_identifiers (person_id, type) WHERE superseded_at IS NULL');

        // Counters for generated identifiers; incremented under row lock.
        Schema::create('identifier_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 150)->unique();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('person_id')->unique()->constrained('persons');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // null when sign-in is only through the identity provider
            $table->string('oidc_subject')->nullable()->unique();
            $table->string('status', 20)->default('active'); // active, suspended, disabled
            $table->boolean('mfa_required')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('email')->nullable();
            $table->boolean('successful');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique(); // e.g. students.view
            $table->string('module', 50);
            $table->string('description');
            $table->boolean('sensitive')->default(false);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('workspace', 40)->nullable(); // registry, bursary, academic_planning ...
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        foreach (['permission_role', 'roles', 'permissions', 'login_histories', 'sessions', 'password_reset_tokens', 'users', 'identifier_sequences', 'person_identifiers', 'persons'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
