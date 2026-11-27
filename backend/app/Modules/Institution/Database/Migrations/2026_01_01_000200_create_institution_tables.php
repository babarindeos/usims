<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // university, campus, college, faculty, department, directorate, unit ...
            $table->string('name');
            $table->boolean('is_academic')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('org_units');
            $table->foreignId('org_unit_type_id')->constrained('org_unit_types');
            $table->string('code', 30);
            $table->string('name');
            $table->string('short_name', 60)->nullable();
            // Materialised path of ids, e.g. "/1/4/17/": scope checks use prefix matching.
            $table->string('path', 500)->index();
            $table->foreignId('head_person_id')->nullable()->constrained('persons');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)->default('active'); // active, inactive, merged, dissolved
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });
        DB::statement("CREATE UNIQUE INDEX org_units_active_code_unique ON org_units (code) WHERE status = 'active'");
        DB::statement('CREATE INDEX org_units_path_prefix ON org_units (path varchar_pattern_ops)');

        // User + role + scope + validity window (PRD 16.1, 16.11).
        Schema::create('role_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('role_id')->constrained('roles');
            $table->string('scope_type', 20); // global, org_unit
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users');
            $table->foreignId('delegated_from_grant_id')->nullable()->constrained('role_grants');
            $table->string('reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['user_id', 'ends_at']);
        });
        DB::statement("ALTER TABLE role_grants ADD CONSTRAINT role_grants_scope_check CHECK ((scope_type = 'global' AND org_unit_id IS NULL) OR (scope_type = 'org_unit' AND org_unit_id IS NOT NULL))");

        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique(); // 2026/2027
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->string('application_status', 20)->default('closed');
            $table->string('admission_status', 20)->default('closed');
            $table->string('registration_status', 20)->default('closed');
            $table->string('course_registration_status', 20)->default('closed');
            $table->string('examination_status', 20)->default('closed');
            $table->string('result_processing_status', 20)->default('closed');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX academic_sessions_one_current ON academic_sessions (is_current) WHERE is_current');
        DB::statement('ALTER TABLE academic_sessions ADD CONSTRAINT academic_sessions_dates CHECK (ends_on > starts_on)');

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions');
            $table->string('name', 50);
            $table->string('code', 10);
            $table->unsignedTinyInteger('sequence');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('registration_opens_on')->nullable();
            $table->date('registration_closes_on')->nullable();
            $table->date('add_drop_opens_on')->nullable();
            $table->date('add_drop_closes_on')->nullable();
            $table->date('exams_start_on')->nullable();
            $table->date('exams_end_on')->nullable();
            $table->date('result_deadline')->nullable();
            $table->string('status', 20)->default('planned'); // planned, active, closed
            $table->timestamps();

            $table->unique(['academic_session_id', 'code']);
            $table->unique(['academic_session_id', 'sequence']);
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions');
            $table->foreignId('semester_id')->nullable()->constrained('semesters');
            $table->string('category', 40); // resumption, registration, lectures, exams, senate, holiday ...
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('audience', 20)->default('all'); // all, students, staff
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units'); // null = whole university
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['academic_session_id', 'starts_on']);
        });

        // Institution-wide settings and branding (key/value, typed in code).
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'calendar_events', 'semesters', 'academic_sessions', 'role_grants', 'org_units', 'org_unit_types'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
