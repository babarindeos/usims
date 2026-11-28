<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delivery of a course master in one semester (PRD 9.1). Moodle courses map to these rows.
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('semester_id')->constrained('semesters');
            $table->string('group_code', 10)->default('A'); // parallel classes of the same course
            $table->foreignId('org_unit_id')->constrained('org_units'); // department delivering it
            $table->unsignedInteger('capacity')->nullable();
            $table->date('registration_opens_on')->nullable(); // overrides the semester window when set
            $table->date('registration_closes_on')->nullable();
            $table->string('delivery_mode', 20)->default('in_person'); // in_person, online, blended
            $table->string('status', 20)->default('draft'); // draft, published, cancelled, closed
            $table->string('lms_shortname', 100)->nullable()->unique();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['course_id', 'semester_id', 'group_code']);
            $table->index(['semester_id', 'org_unit_id', 'status']);
        });

        // Which programme cohorts the offering is for.
        Schema::create('course_offering_programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained('programmes');
            $table->unsignedSmallInteger('level');
            $table->unique(['course_offering_id', 'programme_id', 'level']);
        });

        // Teaching assignments; ended rather than deleted so allocation history is kept (PRD 9.3).
        Schema::create('course_offering_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained('course_offerings');
            $table->foreignId('staff_id')->constrained('staff');
            $table->string('role', 30); // lecturer, coordinator, practical_instructor, teaching_assistant, examiner, moderator
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active'); // active, ended
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->string('reason')->nullable();
            $table->timestamp('flagged_at')->nullable(); // set when the staff member stops being eligible
            $table->string('flag_reason')->nullable();
            $table->string('end_reason')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['staff_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX offering_staff_one_active ON course_offering_staff (course_offering_id, staff_id, role) WHERE status = 'active'");
    }

    public function down(): void
    {
        foreach (['course_offering_staff', 'course_offering_programmes', 'course_offerings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
