<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Course master: one row per permanent course, shared by every programme that uses it (PRD 8.3).
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 20)->unique(); // stored as "CSC 301"
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('credit_units');
            $table->unsignedSmallInteger('level');
            $table->unsignedTinyInteger('semester_sequence')->nullable(); // usual semester; curricula can differ
            $table->foreignId('org_unit_id')->constrained('org_units'); // owning department
            $table->string('course_type', 30)->default('departmental'); // departmental, faculty, university, general_studies
            $table->boolean('has_practical')->default(false);
            $table->unsignedSmallInteger('lecture_hours')->nullable();
            $table->unsignedSmallInteger('practical_hours')->nullable();
            $table->string('status', 20)->default('active'); // active, retired
            $table->timestamps();
            $table->index(['org_unit_id', 'level']);
        });

        Schema::create('course_requisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('required_course_id')->constrained('courses');
            $table->string('type', 20); // prerequisite (must have passed), corequisite (taken with or before)
            $table->string('requirement', 20)->default('pass'); // pass, registered
            $table->timestamps();
            $table->unique(['course_id', 'required_course_id', 'type']);
        });
        DB::statement('ALTER TABLE course_requisites ADD CONSTRAINT course_requisites_not_self CHECK (course_id <> required_course_id)');

        // A programme's curriculum, versioned; published versions are never edited (PRD 8.2).
        Schema::create('curriculum_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('programme_id')->constrained('programmes');
            $table->unsignedSmallInteger('version');
            $table->string('title');
            $table->foreignId('effective_session_id')->constrained('academic_sessions'); // first entry cohort it applies to
            $table->unsignedTinyInteger('duration_years');
            $table->unsignedSmallInteger('required_credit_units');
            $table->unsignedTinyInteger('min_semester_units')->nullable();
            $table->unsignedTinyInteger('max_semester_units')->nullable();
            $table->string('status', 20)->default('draft'); // draft, in_review, returned, approved, published, retired, rejected
            $table->foreignId('based_on_id')->nullable()->constrained('curriculum_versions');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->timestamp('retired_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['programme_id', 'version']);
        });

        Schema::create('elective_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_version_id')->constrained('curriculum_versions')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->unsignedSmallInteger('level');
            $table->unsignedTinyInteger('semester_sequence');
            $table->unsignedTinyInteger('min_units')->default(0);
            $table->unsignedTinyInteger('max_units')->nullable();
            $table->timestamps();
            $table->unique(['curriculum_version_id', 'code']);
        });

        Schema::create('curriculum_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_version_id')->constrained('curriculum_versions')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses');
            $table->unsignedSmallInteger('level');
            $table->unsignedTinyInteger('semester_sequence');
            $table->string('category', 30); // core, elective, required_elective, general_studies
            $table->unsignedTinyInteger('credit_units'); // copied from the course when mapped
            $table->foreignId('elective_group_id')->nullable()->constrained('elective_groups');
            $table->timestamps();
            $table->unique(['curriculum_version_id', 'course_id']);
            $table->index(['curriculum_version_id', 'level', 'semester_sequence']);
        });

        // Database-level guard: course mappings and elective groups of a version that is no longer a
        // draft cannot change, whatever code path tries.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION curriculum_locked_guard() RETURNS trigger AS $$
            DECLARE v_status text;
            BEGIN
                SELECT status INTO v_status FROM curriculum_versions
                    WHERE id = COALESCE(NEW.curriculum_version_id, OLD.curriculum_version_id);
                IF v_status IS NOT NULL AND v_status NOT IN ('draft', 'returned') THEN
                    RAISE EXCEPTION 'curriculum version is % and cannot be changed', v_status;
                END IF;
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER curriculum_courses_locked BEFORE INSERT OR UPDATE OR DELETE ON curriculum_courses
                FOR EACH ROW EXECUTE FUNCTION curriculum_locked_guard();
            CREATE TRIGGER elective_groups_locked BEFORE INSERT OR UPDATE OR DELETE ON elective_groups
                FOR EACH ROW EXECUTE FUNCTION curriculum_locked_guard();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS curriculum_courses_locked ON curriculum_courses; DROP TRIGGER IF EXISTS elective_groups_locked ON elective_groups; DROP FUNCTION IF EXISTS curriculum_locked_guard();');
        foreach (['curriculum_courses', 'elective_groups', 'curriculum_versions', 'course_requisites', 'courses'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
